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

        $publicKey = <<<'PEM'
-----BEGIN PUBLIC KEY-----
    MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEC9cGYkeACdl+z5bV/D0G6d25ZQVD
    SKPkyzPBsPmI7K1vI+1g7jMXjLlb4+KDXKTABbGVfo63FeEi+5ABTf7y2g==
-----END PUBLIC KEY-----
PEM;

        $tokenResponse = $this->postJson('/api/landlords/tokens', [
            'public_key' => $publicKey,
            'device_name' => 'mobile-interoperability-fixture',
        ]);

        $tokenResponse->assertCreated();

        $token = $tokenResponse->json('data.token');
        $timestamp = '1714252800';
        $nonce = 'nonce-123';
        $body = '{"phone_number":"+421900111222"}';
        $signature = 'MEYCIQCMx8bQS40cQUaFjv9/mwEqG+nWzoM/0RHANVXUtY2t/QIhAIZhYNJ0fwbYObp2LWYF3F5tBSd2/pyhwwV4GvfiDZTg';

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
            'status' => InvitationStatus::Pending->value,
        ]);

        Notification::assertSentOnDemand(InvitationSmsNotification::class, function (InvitationSmsNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $channels === ['vonage'];
        });

        Carbon::setTestNow();
    }
}
