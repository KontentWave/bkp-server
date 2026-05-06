<?php

namespace Tests\Feature;

use App\Notifications\InvitationSmsNotification;
use App\Enums\InvitationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileInteroperabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function laravel_accepts_mobile_signed_invitation_fixture(): void
    {
        Notification::fake();

        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
            'device_name' => 'mobile-interoperability-fixture',
        ]);

        $tokenResponse->assertCreated();

        $token = $tokenResponse->json('data.token');
        $timestamp = '1714252800';
        $nonce = 'nonce-123';
        $payload = [
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/invitations', $timestamp, $nonce);

        Carbon::setTestNow(Carbon::createFromTimestampUTC((int) $timestamp));

        $response = $this->call('POST', '/api/invitations', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);

        $response->assertCreated();
        $response->assertJsonPath('data.status', InvitationStatus::Pending->value);

        $this->assertDatabaseHas('invitations', [
            'phone_number' => '+421900111222',
            'invited_role' => 'escort',
            'escort_external_id' => 29637,
            'status' => InvitationStatus::Pending->value,
        ]);

        Notification::assertSentOnDemand(InvitationSmsNotification::class, function (InvitationSmsNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $channels === ['vonage'];
        });

        Carbon::setTestNow();
    }
}
