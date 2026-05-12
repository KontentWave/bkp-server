<?php

namespace App\Services\EscortAds;

use Carbon\CarbonImmutable;

class AmaterkyEscortAdScraper implements EscortAdScraper
{
    public function scrapeByExternalId(int $externalId): EscortAdSnapshot
    {
        return $this->scrapeByUrl($this->buildAdUrl($externalId));
    }

    public function scrapeByUrl(string $adUrl): EscortAdSnapshot
    {
        $externalId = $this->extractExternalIdFromUrl($adUrl);
        $html = $this->fetchHtml($adUrl);
        $state = $this->detectState($html);

        if ($state !== 'active') {
            throw new EscortAdScrapeException(match ($state) {
                'disabled_by_submitter' => 'The escort ad is not currently active for public phone verification.',
                default => 'The escort ad is not currently active.',
            });
        }

        $phoneNumber = $this->extractPhoneNumber($html);

        if ($phoneNumber === null) {
            throw new EscortAdScrapeException('The escort ad does not currently expose a phone number.');
        }

        return new EscortAdSnapshot(
            externalId: $externalId,
            adUrl: $adUrl,
            phoneNumber: $phoneNumber,
            state: $state,
            scrapedAt: CarbonImmutable::now(),
        );
    }

    private function buildAdUrl(int $externalId): string
    {
        return rtrim((string) config('services.amaterky.base_url', 'https://amaterky.sk'), '/').'/'.$externalId;
    }

    private function extractExternalIdFromUrl(string $adUrl): int
    {
        if (preg_match('~/(\d+)(?:[/?#]|$)~', $adUrl, $matches) === 1) {
            return (int) $matches[1];
        }

        throw new EscortAdScrapeException('The escort ad URL does not contain a supported ad ID.');
    }

    private function fetchHtml(string $adUrl): string
    {
        $curl = curl_init($adUrl);

        if ($curl === false) {
            throw new EscortAdScrapeException('The escort ad scraper could not initialize the request.', 503, true);
        }

        $proxy = $this->resolveProxy();
        $proxyUsername = (string) config('services.webshare.username', '');
        $proxyPassword = (string) config('services.webshare.password', '');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => (int) config('services.webshare.connect_timeout', 10),
            CURLOPT_TIMEOUT => (int) config('services.webshare.timeout', 20),
            CURLOPT_USERAGENT => (string) config('services.amaterky.user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0 Safari/537.36'),
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: sk-SK,sk;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
        ]);

        if ($proxy !== null) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
        }

        if ($proxyUsername !== '' || $proxyPassword !== '') {
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, sprintf('%s:%s', $proxyUsername, $proxyPassword));
        }

        $html = curl_exec($curl);

        if ($html === false) {
            $message = curl_error($curl) ?: 'Unknown scraper transport error.';
            curl_close($curl);

            throw new EscortAdScrapeException('The escort ad scraper request failed: '.$message, 503, true);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($statusCode >= 400) {
            throw new EscortAdScrapeException(
                'The escort ad scraper received HTTP '.$statusCode.'.',
                503,
                $this->isTransientHttpStatus($statusCode),
            );
        }

        return (string) $html;
    }

    private function resolveProxy(): ?string
    {
        $configuredProxy = trim((string) config('services.webshare.proxy', ''));

        if ($configuredProxy !== '') {
            return $configuredProxy;
        }

        $host = trim((string) config('services.webshare.host', ''));
        $port = trim((string) config('services.webshare.port', ''));

        if ($host === '' || $port === '') {
            return null;
        }

        return sprintf('http://%s:%s', $host, $port);
    }

    private function detectState(string $html): string
    {
        $plainText = $this->plainText($html);

        if (str_contains(mb_strtolower($plainText), 'vypnutý zadávateľom')) {
            return 'disabled_by_submitter';
        }

        return 'active';
    }

    private function extractPhoneNumber(string $html): ?string
    {
        $telHrefMatches = [];
        preg_match_all('/href\s*=\s*["\']tel:([^"\']+)["\']/i', $html, $telHrefMatches);

        $normalizedFromTelLinks = collect($telHrefMatches[1] ?? [])
            ->map(fn (string $candidate): ?string => $this->normalizePhoneNumber($candidate))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($normalizedFromTelLinks) === 1) {
            return $normalizedFromTelLinks[0];
        }

        if (count($normalizedFromTelLinks) > 1) {
            throw new EscortAdScrapeException('The escort ad exposed multiple phone numbers in tel links, so the scraper refused to guess.');
        }

        $plainText = $this->plainText($html);

        preg_match_all('/(?:\+421|00421|0)\s*\d(?:[\s-]*\d){8,11}/', $plainText, $matches);

        $normalized = collect($matches[0] ?? [])
            ->map(fn (string $candidate): ?string => $this->normalizePhoneNumber($candidate))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($normalized) === 0) {
            return null;
        }

        if (count($normalized) > 1) {
            throw new EscortAdScrapeException('The escort ad exposed multiple phone numbers, so the scraper refused to guess.');
        }

        return $normalized[0];
    }

    private function plainText(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
    }

    private function normalizePhoneNumber(string $input): ?string
    {
        $normalized = preg_replace('/[^\d+]/', '', trim($input)) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '421') && ! str_starts_with($normalized, '+')) {
            $normalized = '+'.$normalized;
        }

        if (preg_match('/^0\d{9}$/', $normalized) === 1) {
            $normalized = '+421'.substr($normalized, 1);
        }

        return preg_match('/^\+\d{10,15}$/', $normalized) === 1
            ? $normalized
            : null;
    }

    private function isTransientHttpStatus(int $statusCode): bool
    {
        return in_array($statusCode, [403, 408, 425, 429], true) || $statusCode >= 500;
    }
}
