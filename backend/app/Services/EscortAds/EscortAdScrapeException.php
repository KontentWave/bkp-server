<?php

namespace App\Services\EscortAds;

use RuntimeException;

class EscortAdScrapeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly bool $transient = false,
    )
    {
        parent::__construct($message);
    }
}
