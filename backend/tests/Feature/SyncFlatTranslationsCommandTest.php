<?php

namespace Tests\Feature;

use App\Services\Translations\LibreTranslateProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncFlatTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_pulls_jobs_from_upstream_and_completes_them_with_local_translations(): void
    {
        Config::set('services.flat_translation.upstream_base_url', 'https://bkp-server.zafo-forum.sk');
        Config::set('services.flat_translation.worker_token', 'worker-secret');
        Config::set('services.flat_translation.provider_name', 'local-libretranslate');

        Http::fake([
            'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs*' => Http::response([
                'data' => [
                    [
                        'id' => 10,
                        'flat_id' => 1,
                        'field_name' => 'title',
                        'language' => 'en',
                        'source_text' => 'Byt na prenajom',
                        'source_hash' => hash('sha256', 'Byt na prenajom'),
                        'status' => 'pending',
                    ],
                ],
            ]),
            'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs/10' => Http::response([
                'data' => [
                    'id' => 10,
                    'status' => 'ready',
                ],
            ]),
        ]);

        $this->mock(LibreTranslateProxy::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('translate')
                ->once()
                ->with('Byt na prenajom', 'en')
                ->andReturn('Flat for rent');
        });

        $this->artisan('translations:sync-flat-cache --limit=5')
            ->expectsOutput('Completed translation job 10 for en.')
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs?limit=5'
                && $request->hasHeader('X-Translation-Worker-Token', 'worker-secret');
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs/10'
                && $request['status'] === 'ready'
                && $request['translated_text'] === 'Flat for rent'
                && $request['provider'] === 'local-libretranslate';
        });
    }

    #[Test]
    public function it_marks_jobs_failed_when_local_translation_throws(): void
    {
        Config::set('services.flat_translation.upstream_base_url', 'https://bkp-server.zafo-forum.sk');
        Config::set('services.flat_translation.worker_token', 'worker-secret');

        Http::fake([
            'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs*' => Http::response([
                'data' => [
                    [
                        'id' => 11,
                        'flat_id' => 1,
                        'field_name' => 'description',
                        'language' => 'ru',
                        'source_text' => 'Velky byt v centre.',
                        'source_hash' => hash('sha256', 'Velky byt v centre.'),
                        'status' => 'pending',
                    ],
                ],
            ]),
            'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs/11' => Http::response([
                'data' => [
                    'id' => 11,
                    'status' => 'failed',
                ],
            ]),
        ]);

        $this->mock(LibreTranslateProxy::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('translate')
                ->once()
                ->with('Velky byt v centre.', 'ru')
                ->andThrow(new \RuntimeException('local libretranslate unavailable'));
        });

        $this->artisan('translations:sync-flat-cache')
            ->expectsOutput('Marked translation job 11 as failed for ru.')
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://bkp-server.zafo-forum.sk/api/internal/flat-translation-jobs/11'
                && $request['status'] === 'failed'
                && $request['failure_message'] === 'local libretranslate unavailable';
        });
    }
}
