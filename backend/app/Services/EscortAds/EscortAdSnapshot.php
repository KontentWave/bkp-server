<?php

namespace App\Services\EscortAds;

use Carbon\CarbonImmutable;

readonly class EscortAdSnapshot
{
    public function __construct(
        public int $externalId,
        public string $adUrl,
        public string $phoneNumber,
        public string $state,
        public CarbonImmutable $scrapedAt,
    ) {
    }
}
