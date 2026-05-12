<?php

namespace Tests;

use App\Services\EscortAds\EscortAdScraper;
use App\Services\EscortAds\EscortAdSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use JsonException;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected function fakeEscortAdScraper(array $snapshotsByExternalId = [], array $snapshotsByUrl = []): void
    {
        $this->app->instance(EscortAdScraper::class, new class($snapshotsByExternalId, $snapshotsByUrl) implements EscortAdScraper {
            public function __construct(
                private readonly array $snapshotsByExternalId,
                private readonly array $snapshotsByUrl,
            ) {
            }

            public function scrapeByExternalId(int $externalId): EscortAdSnapshot
            {
                $value = $this->snapshotsByExternalId[$externalId]
                    ?? throw new \RuntimeException('Missing fake scraper snapshot for external ID '.$externalId);

                if ($value instanceof Throwable) {
                    throw $value;
                }

                return $value;
            }

            public function scrapeByUrl(string $adUrl): EscortAdSnapshot
            {
                if (array_key_exists($adUrl, $this->snapshotsByUrl)) {
                    $value = $this->snapshotsByUrl[$adUrl];

                    if ($value instanceof Throwable) {
                        throw $value;
                    }

                    return $value;
                }

                if (preg_match('~/(\d+)(?:[/?#]|$)~', $adUrl, $matches) === 1) {
                    $externalId = (int) $matches[1];

                    if (array_key_exists($externalId, $this->snapshotsByExternalId)) {
                        $value = $this->snapshotsByExternalId[$externalId];

                        if ($value instanceof Throwable) {
                            throw $value;
                        }

                        return $value;
                    }
                }

                throw new \RuntimeException('Missing fake scraper snapshot for URL '.$adUrl);
            }
        });
    }

    protected function makeEscortAdSnapshot(int $externalId, string $phoneNumber, ?string $adUrl = null, string $state = 'active'): EscortAdSnapshot
    {
        return new EscortAdSnapshot(
            externalId: $externalId,
            adUrl: $adUrl ?? 'https://amaterky.sk/'.$externalId,
            phoneNumber: $phoneNumber,
            state: $state,
            scrapedAt: CarbonImmutable::now(),
        );
    }

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

        return $this->signHardwareContent($encodedPayload, $privateKey, $method, $path, $timestamp, $nonce);
    }

    protected function signHardwareContent(string $content, string $privateKey, string $method, string $path, string $timestamp, string $nonce): string
    {
        $canonicalPayload = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $content),
        ]);

        openssl_sign($canonicalPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    protected function signHardwareBodyHash(string $bodyHash, string $privateKey, string $method, string $path, string $timestamp, string $nonce): string
    {
        $canonicalPayload = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            $bodyHash,
        ]);

        openssl_sign($canonicalPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
