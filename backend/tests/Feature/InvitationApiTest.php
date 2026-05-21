<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Jobs\RetryEscortAdScrapeJob;
use App\Jobs\SendSmsMessageJob;
use App\Models\Escort;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Services\EscortAds\EscortAdScrapeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function landlord_token_issuance_returns_verified_landlord_session(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $response = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
            'device_name' => 'ios-share-extension',
        ]);

        $landlord = Landlord::query()
            ->where('public_key', rtrim($publicKey))
            ->first();

        $this->assertNotNull($landlord);

        $response->assertCreated()
            ->assertJsonPath('data.actor_type', 'landlord')
            ->assertJsonPath('data.actor_id', $landlord->id)
            ->assertJsonPath('data.is_verified', true);
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('landlords', [
            'id' => $landlord->id,
            'public_key' => rtrim($publicKey),
            'is_verified' => true,
        ]);
    }

    #[Test]
    public function invitation_creation_sends_generic_sms(): void
    {
        config()->set('services.smstools.local_override_phone', null);
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421900111222'),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $tokenResponse->assertCreated();

        $landlordId = $tokenResponse->json('data.landlord_id');
        $token = $tokenResponse->json('data.token');
        $payload = [
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.phone_number', '+421900111222')
            ->assertJsonPath('data.delivery_phone_number', '+421900111222')
            ->assertJsonPath('data.delivery_overridden', false);

        $this->assertDatabaseHas('invitations', [
            'landlord_id' => $landlordId,
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'status' => InvitationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class, function (SendSmsMessageJob $job): bool {
            return $job->phoneNumber === '+421900111222'
                && $job->message === 'You have been invited. Use code '.$job->messageOtpCode().' to continue.';
        });
    }

    #[Test]
    public function invitation_creation_routes_sms_to_the_local_override_phone_when_configured(): void
    {
        config()->set('services.smstools.local_override_phone', '+421917047260');
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421947188089'),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $tokenResponse->assertCreated();

        $token = $tokenResponse->json('data.token');
        $payload = [
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.phone_number', '+421947188089')
            ->assertJsonPath('data.delivery_phone_number', '+421917047260')
            ->assertJsonPath('data.delivery_overridden', true);

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421947188089',
            'escort_external_id' => 29637,
            'status' => InvitationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class, function (SendSmsMessageJob $job): bool {
            return $job->phoneNumber === '+421917047260';
        });
    }

    #[Test]
    public function browser_invitation_creation_accepts_authenticated_landlord_without_hardware_signature(): void
    {
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421900111222'),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
            'device_name' => 'chrome-trust-anchor',
        ]);

        $tokenResponse->assertCreated();

        $landlordId = $tokenResponse->json('data.landlord_id');
        $token = $tokenResponse->json('data.token');

        $response = $this->withToken($token)->postJson('/api/browser/invitations', [
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('invitations', [
            'landlord_id' => $landlordId,
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'status' => InvitationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function landlord_invitation_creation_sends_generic_sms(): void
    {
        Queue::fake();

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $tokenResponse->assertCreated();

        $landlordId = $tokenResponse->json('data.landlord_id');
        $token = $tokenResponse->json('data.token');
        $payload = [
            'phone_number' => '+421900111223',
            'invited_role' => 'landlord',
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.invited_role', 'landlord');

        $this->assertDatabaseHas('invitations', [
            'landlord_id' => $landlordId,
            'phone_number' => '+421900111223',
            'invited_role' => 'landlord',
            'escort_external_id' => null,
            'status' => InvitationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function escort_can_create_a_landlord_invitation(): void
    {
        Queue::fake();

        [$privateKey, $publicKey] = $this->generateEcKeyPair();
        $escort = Escort::query()->create([
            'phone_number' => '+421900111224',
            'public_key' => rtrim($publicKey),
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = [
            'phone_number' => '+421900111225',
            'invited_role' => 'landlord',
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.invited_role', 'landlord')
            ->assertJsonPath('data.phone_number', '+421900111225');

        $this->assertDatabaseHas('invitations', [
            'landlord_id' => null,
            'inviter_type' => Escort::class,
            'inviter_id' => $escort->id,
            'phone_number' => '+421900111225',
            'invited_role' => 'landlord',
            'status' => InvitationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function escort_invitation_creation_rejects_when_submitted_phone_does_not_match_scraped_ad_phone(): void
    {
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421900111222'),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $token = $tokenResponse->json('data.token');
        $payload = [
            'phone_number' => '+421900111999',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The provided phone number does not match the current phone visible on the escort ad.');

        Queue::assertNotPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function escort_invitation_creation_allows_phone_mismatch_when_local_bypass_is_enabled(): void
    {
        config()->set('services.amaterky.enforce_phone_match', false);
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421947192590'),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $token = $tokenResponse->json('data.token');
        $payload = [
            'phone_number' => '+421900111999',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.phone_number', '+421900111999');

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421900111999',
            'escort_external_id' => 29637,
        ]);

        Queue::assertPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function escort_invitation_creation_queues_retry_when_scraping_fails_transiently(): void
    {
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => new EscortAdScrapeException('Temporary proxy failure.', 503, true),
        ]);

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
        ]);

        $token = $tokenResponse->json('data.token');
        $payload = [
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/invitations', $payload);

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'Temporary proxy failure.');

        Queue::assertPushed(RetryEscortAdScrapeJob::class, function (RetryEscortAdScrapeJob $job): bool {
            return $job->escortExternalId === 29637
                && $job->escortAdUrl === null
                && $job->reason === 'invitation';
        });

        Queue::assertNotPushed(SendSmsMessageJob::class);
    }

    #[Test]
    public function otp_verification_stores_public_key_and_returns_token(): void
    {
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421900111222'),
        ]);
        $landlord = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => '+421900111222',
            'escort_ad_url' => 'https://amaterky.sk/29637',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'phone_scraped_at' => now(),
            'otp_token' => hash('sha256', '8492'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $publicKey = "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----";

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '8492',
            'public_key' => $publicKey,
        ]);

        $escort = \App\Models\Escort::query()
            ->where('phone_number', '+421900111222')
            ->first();

        $this->assertNotNull($escort);

        $response->assertOk()
            ->assertJsonPath('data.actor_type', 'escort')
            ->assertJsonPath('data.actor_id', $escort->id)
            ->assertJsonPath('data.phone_number', '+421900111222');
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('escorts', [
            'id' => $escort->id,
            'external_id' => 29637,
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421900111222',
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

    #[Test]
    public function otp_verification_rejects_incorrect_otp(): void
    {
        $landlord = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'otp_token' => hash('sha256', '8492'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '1111',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----",
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('escorts', [
            'phone_number' => '+421900111222',
        ]);

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421900111222',
            'status' => InvitationStatus::Pending->value,
        ]);
    }

    #[Test]
    public function escort_otp_verification_rejects_when_the_scraped_ad_phone_has_changed(): void
    {
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421900111999'),
        ]);
        $landlord = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => '+421900111222',
            'escort_ad_url' => 'https://amaterky.sk/29637',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'phone_scraped_at' => now(),
            'otp_token' => hash('sha256', '8492'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '8492',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----",
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The escort ad phone changed before activation. Request a new invitation.');
    }

    #[Test]
    public function escort_otp_verification_allows_phone_change_when_local_bypass_is_enabled(): void
    {
        config()->set('services.amaterky.enforce_phone_match', false);
        $this->fakeEscortAdScraper([
            29637 => $this->makeEscortAdSnapshot(29637, '+421947192590'),
        ]);
        $landlord = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => '+421900111222',
            'escort_ad_url' => 'https://amaterky.sk/29637',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'phone_scraped_at' => now(),
            'otp_token' => hash('sha256', '8492'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $publicKey = "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----";

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '8492',
            'public_key' => $publicKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.actor_type', 'escort')
            ->assertJsonPath('data.phone_number', '+421900111222');

        $this->assertDatabaseHas('escorts', [
            'phone_number' => '+421900111222',
            'external_id' => 29637,
            'public_key' => $publicKey,
        ]);
    }

    #[Test]
    public function escort_otp_verification_queues_retry_when_revalidation_fails_transiently(): void
    {
        Queue::fake();
        $this->fakeEscortAdScraper([
            29637 => new EscortAdScrapeException('Temporary proxy failure.', 503, true),
        ]);
        $landlord = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => '+421900111222',
            'escort_ad_url' => 'https://amaterky.sk/29637',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'phone_scraped_at' => now(),
            'otp_token' => hash('sha256', '8492'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '8492',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----",
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'Temporary proxy failure.');

        Queue::assertPushed(RetryEscortAdScrapeJob::class, function (RetryEscortAdScrapeJob $job): bool {
            return $job->escortExternalId === 29637
                && $job->escortAdUrl === 'https://amaterky.sk/29637'
                && $job->reason === 'verification';
        });
    }

    #[Test]
    public function landlord_otp_verification_stores_public_key_and_returns_token(): void
    {
        $inviter = Landlord::query()->create();

        Invitation::query()->create([
            'landlord_id' => $inviter->id,
            'phone_number' => '+421900111223',
            'invited_role' => 'landlord',
            'otp_token' => hash('sha256', '1234'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $publicKey = "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----";

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111223',
            'otp' => '1234',
            'public_key' => $publicKey,
        ]);

        $landlord = Landlord::query()
            ->where('phone_number', '+421900111223')
            ->first();

        $this->assertNotNull($landlord);

        $response->assertOk()
            ->assertJsonPath('data.actor_type', 'landlord')
            ->assertJsonPath('data.actor_id', $landlord->id)
            ->assertJsonPath('data.landlord_id', $landlord->id)
            ->assertJsonPath('data.phone_number', '+421900111223');
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('landlords', [
            'id' => $landlord->id,
            'phone_number' => '+421900111223',
            'public_key' => $publicKey,
            'is_verified' => true,
        ]);

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421900111223',
            'invited_role' => 'landlord',
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

}
