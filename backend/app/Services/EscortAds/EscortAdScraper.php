<?php

namespace App\Services\EscortAds;

interface EscortAdScraper
{
    public function scrapeByExternalId(int $externalId): EscortAdSnapshot;

    public function scrapeByUrl(string $adUrl): EscortAdSnapshot;
}
