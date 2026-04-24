<?php

namespace Tests\Feature;

use App\Models\Escort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReverbAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reverb_websocket_connection_requires_auth(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.1',
            'socket_id' => '1234.5678',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function reverb_websocket_connection_accepts_authenticated_signed_request(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $escort = Escort::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = [
            'channel_name' => 'private-chat.'.$escort->id,
            'socket_id' => '1234.5678',
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/broadcasting/auth', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/broadcasting/auth', $payload);

        $response->assertOk();
    }
}