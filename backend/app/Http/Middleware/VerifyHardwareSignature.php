<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class VerifyHardwareSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hardware-Signature');
        $nonce = $request->header('X-Hardware-Nonce');
        $timestamp = $request->header('X-Hardware-Timestamp');
        $actor = $request->user();

        if (! is_string($signature) || ! is_string($nonce) || ! is_string($timestamp) || $actor === null) {
            return response()->json(['message' => 'Hardware signature is required.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($actor === null || ! is_string($actor->public_key) || $actor->public_key === '') {
            return response()->json(['message' => 'Unknown hardware identity.'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $actor->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Authenticated device token is required.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! ctype_digit($timestamp)) {
            return response()->json(['message' => 'Hardware timestamp is malformed.'], Response::HTTP_UNAUTHORIZED);
        }

        $timestampValue = (int) $timestamp;
        $allowedSkew = (int) env('HARDWARE_SIGNATURE_TTL_SECONDS', 300);

        if (abs(now()->timestamp - $timestampValue) > $allowedSkew) {
            return response()->json(['message' => 'Hardware timestamp has expired.'], Response::HTTP_UNAUTHORIZED);
        }

        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return response()->json(['message' => 'Hardware signature is malformed.'], Response::HTTP_UNAUTHORIZED);
        }

        $publicKey = openssl_pkey_get_public($actor->public_key);

        if ($publicKey === false) {
            return response()->json(['message' => 'Stored public key is invalid.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->buildCanonicalPayload($request, $timestamp, $nonce);
        $verificationResult = openssl_verify($payload, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verificationResult !== 1) {
            return response()->json(['message' => 'Hardware signature verification failed.'], Response::HTTP_UNAUTHORIZED);
        }

        $nonceHash = hash('sha256', $nonce);

        try {
            DB::table('hardware_request_nonces')->insert([
                'personal_access_token_id' => $accessToken->id,
                'nonce_hash' => $nonceHash,
                'used_at' => now(),
                'expires_at' => now()->addSeconds($allowedSkew),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            return response()->json(['message' => 'Hardware nonce has already been used.'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('hardware_signature_verified', true);
        $request->attributes->set('hardware_actor', $actor);

        return $next($request);
    }

    private function buildCanonicalPayload(Request $request, string $timestamp, string $nonce): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $queryString = $request->getQueryString();

        if (is_string($queryString) && $queryString !== '') {
            $path .= '?'.$queryString;
        }

        return implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            $path,
            hash('sha256', $request->getContent()),
        ]);
    }
}
