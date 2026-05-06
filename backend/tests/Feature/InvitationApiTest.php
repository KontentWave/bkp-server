<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Notifications\InvitationSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
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

        $response->assertCreated()
            ->assertJsonPath('data.actor_type', 'landlord')
            ->assertJsonPath('data.actor_id', 1)
            ->assertJsonPath('data.is_verified', true);
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('landlords', [
            'public_key' => rtrim($publicKey),
            'is_verified' => true,
        ]);
    }

    #[Test]
    public function invitation_creation_sends_generic_sms(): void
    {
        Notification::fake();

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

        $response->assertCreated();

        $this->assertDatabaseHas('invitations', [
            'landlord_id' => $landlordId,
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'status' => InvitationStatus::Pending->value,
        ]);

        Notification::assertSentOnDemand(InvitationSmsNotification::class, function (InvitationSmsNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $message = $notification->toVonage($notifiable);

            return $channels === ['vonage']
                && $message->content === 'You have been invited. Use code '.$notification->toArray($notifiable)['otp_code'].' to continue.';
        });
    }

    #[Test]
    public function browser_invitation_creation_accepts_authenticated_landlord_without_hardware_signature(): void
    {
        Notification::fake();

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

        Notification::assertSentOnDemand(InvitationSmsNotification::class);
    }

    #[Test]
    public function landlord_invitation_creation_sends_generic_sms(): void
    {
        Notification::fake();

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

        Notification::assertSentOnDemand(InvitationSmsNotification::class);
    }

    #[Test]
    public function otp_verification_stores_public_key_and_returns_token(): void
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

        $publicKey = "-----BEGIN PUBLIC KEY-----\nTEST-KEY\n-----END PUBLIC KEY-----";

        $response = $this->postJson('/api/verify', [
            'phone_number' => '+421900111222',
            'otp' => '8492',
            'public_key' => $publicKey,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.actor_type', 'escort')
            ->assertJsonPath('data.actor_id', 1)
            ->assertJsonPath('data.phone_number', '+421900111222');
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('escorts', [
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

        $response->assertOk()
            ->assertJsonPath('data.actor_type', 'landlord')
            ->assertJsonPath('data.actor_id', 2)
            ->assertJsonPath('data.landlord_id', 2)
            ->assertJsonPath('data.phone_number', '+421900111223');
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('landlords', [
            'id' => 2,
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
