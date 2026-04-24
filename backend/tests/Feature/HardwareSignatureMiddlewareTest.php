<?php

namespace Tests\Feature;

use App\Models\Escort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HardwareSignatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function middleware_accepts_valid_hardware_signature(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $escort = Escort::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = ['message' => 'signed'];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/protected/ping', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/protected/ping', $payload);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    #[Test]
    public function middleware_rejects_invalid_hardware_signature(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $escort = Escort::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = ['message' => 'tampered'];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest(['message' => 'original'], $privateKey, 'POST', '/api/protected/ping', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/protected/ping', $payload);

        $response->assertUnauthorized();
    }

    #[Test]
    public function middleware_rejects_replayed_nonce(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $escort = Escort::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = ['message' => 'signed'];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/protected/ping', $timestamp, $nonce);

        $headers = [
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ];

        $this->withToken($token)->withHeaders($headers)->postJson('/api/protected/ping', $payload)->assertOk();
        $this->withToken($token)->withHeaders($headers)->postJson('/api/protected/ping', $payload)->assertUnauthorized();
    }

    #[Test]
    public function middleware_rejects_stale_hardware_timestamp(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $escort = Escort::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => $publicKey,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;
        $payload = ['message' => 'signed'];
        $timestamp = (string) now()->subMinutes(10)->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/protected/ping', $timestamp, $nonce);

        $response = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $nonce,
            'X-Hardware-Timestamp' => $timestamp,
            'X-Hardware-Signature' => $signature,
        ])->postJson('/api/protected/ping', $payload);

        $response->assertUnauthorized();
    }
}
