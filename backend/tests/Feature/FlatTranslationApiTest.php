<?php

namespace Tests\Feature;

use App\Http\Resources\FlatResource;
use App\Models\Flat;
use App\Models\FlatTranslation;
use App\Models\Landlord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlatTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function flat_save_creates_pending_translation_jobs_for_title_and_description(): void
    {
        Config::set('services.flat_translation.target_languages', ['en', 'ru']);

        $flat = Flat::query()->create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Byt na prenajom',
            'description' => 'Velky byt v centre.',
        ]);

        $this->assertDatabaseCount('flat_translations', 4);
        $this->assertDatabaseHas('flat_translations', [
            'flat_id' => $flat->id,
            'field_name' => 'title',
            'language' => 'en',
            'status' => FlatTranslation::STATUS_PENDING,
            'source_text' => 'Byt na prenajom',
        ]);
        $this->assertDatabaseHas('flat_translations', [
            'flat_id' => $flat->id,
            'field_name' => 'description',
            'language' => 'ru',
            'status' => FlatTranslation::STATUS_PENDING,
            'source_text' => 'Velky byt v centre.',
        ]);
    }

    #[Test]
    public function updating_title_requeues_only_changed_translation_jobs(): void
    {
        Config::set('services.flat_translation.target_languages', ['en']);

        $flat = Flat::query()->create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Byt na prenajom',
            'description' => 'Velky byt v centre.',
        ]);

        FlatTranslation::query()->where('flat_id', $flat->id)->update([
            'status' => FlatTranslation::STATUS_READY,
            'translated_text' => 'cached value',
            'translated_at' => now(),
        ]);

        $flat->update([
            'title' => 'Byt po rekonstrukcii',
            'contact_phone' => '+421900123456',
        ]);

        $titleTranslation = FlatTranslation::query()
            ->where('flat_id', $flat->id)
            ->where('field_name', 'title')
            ->where('language', 'en')
            ->firstOrFail();
        $descriptionTranslation = FlatTranslation::query()
            ->where('flat_id', $flat->id)
            ->where('field_name', 'description')
            ->where('language', 'en')
            ->firstOrFail();

        $this->assertSame(FlatTranslation::STATUS_PENDING, $titleTranslation->status);
        $this->assertNull($titleTranslation->translated_text);
        $this->assertSame(FlatTranslation::STATUS_READY, $descriptionTranslation->status);
        $this->assertSame('cached value', $descriptionTranslation->translated_text);
    }

    #[Test]
    public function worker_can_poll_and_complete_pending_jobs(): void
    {
        Config::set('services.flat_translation.worker_token', 'worker-secret');
        Config::set('services.flat_translation.target_languages', ['en']);

        $flat = Flat::query()->create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Byt na prenajom',
            'description' => 'Velky byt v centre.',
        ]);

        $response = $this->withHeaders([
            'X-Translation-Worker-Token' => 'worker-secret',
        ])->getJson('/api/internal/flat-translation-jobs?limit=10');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.flat_id', $flat->id);

        $job = FlatTranslation::query()
            ->where('flat_id', $flat->id)
            ->where('field_name', 'title')
            ->firstOrFail();

        $completeResponse = $this->withHeaders([
            'X-Translation-Worker-Token' => 'worker-secret',
        ])->postJson('/api/internal/flat-translation-jobs/'.$job->id, [
            'source_hash' => $job->source_hash,
            'status' => FlatTranslation::STATUS_READY,
            'translated_text' => 'Flat for rent',
            'provider' => 'local-libretranslate',
        ]);

        $completeResponse->assertOk()
            ->assertJsonPath('data.status', FlatTranslation::STATUS_READY);

        $this->assertDatabaseHas('flat_translations', [
            'id' => $job->id,
            'status' => FlatTranslation::STATUS_READY,
            'translated_text' => 'Flat for rent',
            'provider' => 'local-libretranslate',
        ]);
    }

    #[Test]
    public function flat_resource_returns_only_current_ready_translations(): void
    {
        $flat = Flat::query()->create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Byt na prenajom',
            'description' => 'Velky byt v centre.',
        ]);

        FlatTranslation::query()->updateOrCreate([
            'flat_id' => $flat->id,
            'field_name' => 'title',
            'language' => 'en',
        ], [
            'source_hash' => hash('sha256', 'Byt na prenajom'),
            'source_text' => 'Byt na prenajom',
            'translated_text' => 'Flat for rent',
            'status' => FlatTranslation::STATUS_READY,
            'provider' => 'local-libretranslate',
            'translated_at' => now(),
        ]);

        FlatTranslation::query()->updateOrCreate([
            'flat_id' => $flat->id,
            'field_name' => 'description',
            'language' => 'en',
        ], [
            'source_hash' => hash('sha256', 'Velky byt v centre.'),
            'source_text' => 'Velky byt v centre.',
            'translated_text' => 'Large flat in the center.',
            'status' => FlatTranslation::STATUS_READY,
            'provider' => 'local-libretranslate',
            'translated_at' => now(),
        ]);

        FlatTranslation::query()->updateOrCreate([
            'flat_id' => $flat->id,
            'field_name' => 'title',
            'language' => 'ru',
        ], [
            'source_hash' => hash('sha256', 'outdated source'),
            'source_text' => 'outdated source',
            'translated_text' => 'Устаревший перевод',
            'status' => FlatTranslation::STATUS_READY,
            'provider' => 'local-libretranslate',
            'translated_at' => now(),
        ]);

        $payload = FlatResource::make($flat->load('flatTranslations'))->resolve();

        $this->assertSame('Flat for rent', data_get($payload, 'translations.en.title'));
        $this->assertSame('Large flat in the center.', data_get($payload, 'translations.en.description'));
        $this->assertNull(data_get($payload, 'translations.ru.title'));
    }

    private function createLandlord(): Landlord
    {
        return Landlord::query()->create([
            'phone_number' => '+421900000000',
            'public_key' => 'test-public-key',
            'is_verified' => true,
        ]);
    }
}
