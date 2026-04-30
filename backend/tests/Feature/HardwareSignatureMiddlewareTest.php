<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyHardwareSignature;
use App\Models\Escort;
use App\Models\Landlord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
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

    #[Test]
    public function middleware_accepts_raw_multipart_signature_for_flat_photo_upload_path(): void
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();

        $landlord = Landlord::query()->create([
            'public_key' => $publicKey,
            'is_verified' => true,
        ]);

        $accessToken = $landlord->createToken('landlord-device')->accessToken;
        $actor = $landlord->withAccessToken($accessToken);
        $boundary = '----BKPBoundary'.Str::random(24);
        $body = implode("\r\n", [
            '--'.$boundary,
            'Content-Disposition: form-data; name="photo"; filename="flat.jpg"',
            'Content-Type: image/jpeg',
            '',
            'raw-jpeg-payload',
            '--'.$boundary.'--',
            '',
        ]);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent($body, $privateKey, 'POST', '/api/flats/42/photos', $timestamp, $nonce);

        $request = Request::create('/api/flats/42/photos', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
        ], $body);
        $request->setUserResolver(fn () => $actor);

        $middleware = new VerifyHardwareSignature();
        $response = $middleware->handle($request, fn (): Response => response()->json(['ok' => true]));

        $this->assertTrue($request->attributes->get('hardware_signature_verified'));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());
    }
}
