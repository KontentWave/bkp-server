<?php

namespace App\Console\Commands;

use App\Models\FlatTranslation;
use App\Services\Translations\LibreTranslateProxy;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'translations:sync-flat-cache',
    description: 'Pull pending flat translation jobs from an upstream BKP server and complete them using local LibreTranslate',
)]
class SyncFlatTranslationsCommand extends Command
{
    protected $signature = 'translations:sync-flat-cache
        {--limit= : Maximum number of jobs to process in one run}';

    protected $description = 'Pull pending flat translation jobs from an upstream BKP server and complete them using local LibreTranslate';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly LibreTranslateProxy $libreTranslateProxy,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $upstreamBaseUrl = rtrim((string) config('services.flat_translation.upstream_base_url', ''), '/');
        $workerToken = trim((string) config('services.flat_translation.worker_token', ''));
        $providerName = trim((string) config('services.flat_translation.provider_name', 'local-libretranslate'));
        $limit = max(1, (int) ($this->option('limit') ?: config('services.flat_translation.job_batch_size', 20)));

        if ($upstreamBaseUrl === '') {
            $this->error('Set FLAT_TRANSLATION_UPSTREAM_BASE_URL before running translations:sync-flat-cache.');

            return self::FAILURE;
        }

        if ($workerToken === '') {
            $this->error('Set FLAT_TRANSLATION_WORKER_TOKEN before running translations:sync-flat-cache.');

            return self::FAILURE;
        }

        $jobsResponse = $this->http
            ->acceptJson()
            ->withHeaders([
                'X-Translation-Worker-Token' => $workerToken,
            ])
            ->get($upstreamBaseUrl.'/api/internal/flat-translation-jobs', [
                'limit' => $limit,
            ]);

        if ($jobsResponse->failed()) {
            $this->error('Fetching translation jobs failed with status '.$jobsResponse->status().'.');

            return self::FAILURE;
        }

        $jobs = data_get($jobsResponse->json(), 'data', []);

        if (! is_array($jobs) || $jobs === []) {
            $this->line('No pending flat translation jobs.');

            return self::SUCCESS;
        }

        $completedCount = 0;
        $failedCount = 0;
        $requestFailureCount = 0;

        foreach ($jobs as $job) {
            $jobId = (int) data_get($job, 'id');
            $sourceHash = (string) data_get($job, 'source_hash', '');
            $language = (string) data_get($job, 'language', '');
            $sourceText = (string) data_get($job, 'source_text', '');

            if ($jobId <= 0 || $sourceHash === '' || $language === '' || $sourceText === '') {
                $this->warn('Skipping malformed translation job payload.');
                $requestFailureCount++;

                continue;
            }

            try {
                $translatedText = $this->libreTranslateProxy->translate($sourceText, $language);

                $completionPayload = [
                    'source_hash' => $sourceHash,
                    'status' => FlatTranslation::STATUS_READY,
                    'translated_text' => is_string($translatedText) ? $translatedText : $sourceText,
                    'provider' => $providerName,
                ];
            } catch (\Throwable $error) {
                $completionPayload = [
                    'source_hash' => $sourceHash,
                    'status' => FlatTranslation::STATUS_FAILED,
                    'failure_message' => $this->truncateFailureMessage($error->getMessage()),
                    'provider' => $providerName,
                ];
            }

            $completionResponse = $this->http
                ->acceptJson()
                ->withHeaders([
                    'X-Translation-Worker-Token' => $workerToken,
                ])
                ->post($upstreamBaseUrl.'/api/internal/flat-translation-jobs/'.$jobId, $completionPayload);

            if ($completionResponse->failed()) {
                $requestFailureCount++;
                $this->warn('Completing translation job '.$jobId.' failed with status '.$completionResponse->status().'.');

                continue;
            }

            if ($completionPayload['status'] === FlatTranslation::STATUS_READY) {
                $completedCount++;
                $this->line('Completed translation job '.$jobId.' for '.$language.'.');
            } else {
                $failedCount++;
                $this->warn('Marked translation job '.$jobId.' as failed for '.$language.'.');
            }
        }

        $this->table(
            ['completed', 'failed', 'request_failures'],
            [[$completedCount, $failedCount, $requestFailureCount]],
        );

        return $requestFailureCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function truncateFailureMessage(string $message): string
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return 'Translation failed.';
        }

        return mb_substr($trimmed, 0, 500);
    }
}
