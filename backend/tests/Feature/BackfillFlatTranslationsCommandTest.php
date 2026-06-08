<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatTranslation;
use App\Models\Landlord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackfillFlatTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_pending_translation_jobs_for_existing_flats(): void
    {
        Config::set('services.flat_translation.target_languages', ['en', 'ru']);

        Flat::withoutEvents(function (): void {
            $landlord = Landlord::query()->create([
                'phone_number' => '+421900000001',
                'public_key' => 'test-public-key',
                'is_verified' => true,
            ]);

            Flat::query()->create([
                'landlord_id' => $landlord->id,
                'title' => 'Byt na prenajom',
                'description' => 'Velky byt v centre.',
            ]);
        });

        $this->assertDatabaseCount('flat_translations', 0);

        $this->artisan('translations:backfill-flat-cache')
            ->expectsOutput('Seeded translation jobs for 1 flat(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('flat_translations', 4);
        $this->assertDatabaseHas('flat_translations', [
            'field_name' => 'title',
            'language' => 'en',
            'status' => FlatTranslation::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('flat_translations', [
            'field_name' => 'description',
            'language' => 'ru',
            'status' => FlatTranslation::STATUS_PENDING,
        ]);
    }
}
