<?php

namespace App\Console\Commands;

use App\Services\EscortAds\EscortAdScrapeException;
use App\Services\EscortAds\EscortAdScraper;
use Illuminate\Console\Command;

class TestEscortAdScraperCommand extends Command
{
    protected $signature = 'escort:scrape-test
        {target : Escort ad URL or numeric external ID}
        {--no-proxy : Disable the configured Webshare proxy for this run}';

    protected $description = 'Manually test the escort ad scraper against a URL or external ID';

    public function handle(EscortAdScraper $escortAdScraper): int
    {
        $target = trim((string) $this->argument('target'));
        $withoutProxy = (bool) $this->option('no-proxy');

        if ($target === '') {
            $this->components->error('Provide an escort ad URL or numeric external ID.');

            return self::FAILURE;
        }

        if ($withoutProxy) {
            config()->set('services.webshare.proxy', null);
            config()->set('services.webshare.host', null);
            config()->set('services.webshare.port', null);
            config()->set('services.webshare.username', '');
            config()->set('services.webshare.password', '');
        }

        $proxyHost = trim((string) config('services.webshare.host', ''));
        $proxyPort = trim((string) config('services.webshare.port', ''));
        $configuredProxy = trim((string) config('services.webshare.proxy', ''));

        $this->components->twoColumnDetail('Target', $target);
        $this->components->twoColumnDetail('Proxy enabled', $withoutProxy ? 'no' : 'yes');
        $this->components->twoColumnDetail(
            'Proxy endpoint',
            $configuredProxy !== ''
                ? $configuredProxy
                : ($proxyHost !== '' && $proxyPort !== '' ? $proxyHost.':'.$proxyPort : 'not configured')
        );

        try {
            $snapshot = ctype_digit($target)
                ? $escortAdScraper->scrapeByExternalId((int) $target)
                : $escortAdScraper->scrapeByUrl($target);
        } catch (EscortAdScrapeException $exception) {
            $this->components->error($exception->getMessage());
            $this->components->twoColumnDetail('HTTP status', (string) $exception->statusCode);
            $this->components->twoColumnDetail('Transient', $exception->transient ? 'yes' : 'no');

            return self::FAILURE;
        }

        $this->components->info('Scrape succeeded.');
        $this->table(
            ['Field', 'Value'],
            [
                ['external_id', (string) $snapshot->externalId],
                ['ad_url', $snapshot->adUrl],
                ['phone_number', $snapshot->phoneNumber],
                ['state', $snapshot->state],
                ['scraped_at', $snapshot->scrapedAt->toIso8601String()],
            ]
        );

        return self::SUCCESS;
    }
}
