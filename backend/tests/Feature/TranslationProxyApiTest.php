<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranslationProxyApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_translates_a_batch_of_texts_through_the_configured_proxy(): void
    {
        Config::set('services.libretranslate.endpoint', 'https://translate.example.test/translate');

        Http::fake([
            'https://translate.example.test/translate' => Http::response([
                'translatedText' => ['Квартира в аренду', 'Описание квартиры'],
            ]),
        ]);

        $response = $this->postJson('/api/translate', [
            'q' => ['Byt na prenajom', 'Popis bytu'],
            'target' => 'ru',
        ]);

        $response->assertOk()
            ->assertJson([
                'translatedText' => ['Квартира в аренду', 'Описание квартиры'],
            ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://translate.example.test/translate'
                && $request['target'] === 'ru'
                && $request['source'] === 'auto'
                && $request['format'] === 'text'
                && $request['q'] === ['Byt na prenajom', 'Popis bytu'];
        });
    }

    #[Test]
    public function it_returns_service_unavailable_when_proxy_is_not_configured(): void
    {
        Config::set('services.libretranslate.endpoint', '');

        $response = $this->postJson('/api/translate', [
            'q' => 'Byt na prenajom',
            'target' => 'en',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'LibreTranslate endpoint is not configured.');
    }

    #[Test]
    public function it_can_include_debug_metadata_in_the_proxy_response(): void
    {
        Config::set('services.libretranslate.endpoint', 'https://translate.example.test/translate');
        Config::set('services.libretranslate.debug_response', true);

        Http::fake([
            'https://translate.example.test/translate' => Http::response([
                'translatedText' => 'Flat for rent',
            ]),
        ]);

        $response = $this->postJson('/api/translate', [
            'q' => 'Byt na prenajom',
            'target' => 'en',
        ]);

        $response->assertOk()
            ->assertHeader('X-Translation-Proxy', 'bkp-libretranslate')
            ->assertJsonPath('debug.provider', 'libretranslate')
            ->assertJsonPath('debug.endpoint', 'https://translate.example.test/translate')
            ->assertJsonPath('debug.target', 'en')
            ->assertJsonPath('debug.source', 'auto');
    }
}
