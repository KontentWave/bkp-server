<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use JsonException;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array{0: string, 1: string}
     */
    protected function generateEcKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        openssl_pkey_export($resource, $privateKey);

        $details = openssl_pkey_get_details($resource);

        return [$privateKey, $details['key']];
    }

    /**
     * @throws JsonException
     */
    protected function signHardwareRequest(array $payload, string $privateKey, string $method, string $path, string $timestamp, string $nonce): string
    {
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $canonicalPayload = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $encodedPayload),
        ]);

        openssl_sign($canonicalPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
