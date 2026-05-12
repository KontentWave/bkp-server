<?php

namespace App\Jobs;

use App\Services\EscortAds\EscortAdScrapeException;
use App\Services\EscortAds\EscortAdScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryEscortAdScrapeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public function __construct(
        public readonly ?int $escortExternalId,
        public readonly ?string $escortAdUrl,
        public readonly string $reason,
    ) {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(EscortAdScraper $escortAdScraper): void
    {
        try {
            if (is_string($this->escortAdUrl) && $this->escortAdUrl !== '') {
                $escortAdScraper->scrapeByUrl($this->escortAdUrl);

                return;
            }

            if (is_int($this->escortExternalId)) {
                $escortAdScraper->scrapeByExternalId($this->escortExternalId);
            }
        } catch (EscortAdScrapeException $exception) {
            if ($exception->transient) {
                throw $exception;
            }

            Log::warning('Escort ad retry settled as non-transient failure.', [
                'reason' => $this->reason,
                'escort_external_id' => $this->escortExternalId,
                'escort_ad_url' => $this->escortAdUrl,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
