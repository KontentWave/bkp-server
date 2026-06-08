<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $landlord_id
 * @property int|null $municipality_id
 * @property string $title
 * @property string|null $description
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $whatsapp_url
 * @property string|null $telegram_url
 * @property string|null $viber_url
 * @property \App\Models\Municipality|null $municipality
 * @property int|null $votes_count
 * @property bool|null $my_vote
 * @property int|null $landlord_reports_count
 * @property array<int, string> $landlord_report_reasons
 * @property string|null $my_landlord_report_reason
 * @property Collection<int, FlatPhoto> $photos
 * @property Collection<int, FlatReport> $reports
 * @property Collection<int, FlatTranslation> $flatTranslations
 */
class Flat extends Model
{
    use HasFactory;

     private const TRANSLATABLE_FIELDS = ['title', 'description'];

    protected $fillable = [
        'landlord_id',
        'municipality_id',
        'title',
        'description',
        'contact_phone',
        'contact_email',
        'whatsapp_url',
        'telegram_url',
        'viber_url',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $flat): void {
            if (! $flat->wasRecentlyCreated && ! $flat->wasChanged(self::TRANSLATABLE_FIELDS)) {
                return;
            }

            $flat->syncTranslationJobs();
        });
    }

    /**
     * @return BelongsTo<Landlord, $this>
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class);
    }

    /**
     * @return BelongsTo<\App\Models\Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Municipality::class);
    }

    /**
     * @return HasMany<FlatPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FlatPhoto::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<FlatReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(FlatReport::class);
    }

    /**
     * @return HasMany<FlatTranslation, $this>
     */
    public function flatTranslations(): HasMany
    {
        return $this->hasMany(FlatTranslation::class);
    }

    public function syncTranslationJobs(): void
    {
        $targetLanguages = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $language): string => trim((string) $language),
                config('services.flat_translation.target_languages', ['en', 'ru', 'es']),
            ),
            static fn (string $language): bool => $language !== '' && $language !== 'sk',
        )));

        if ($targetLanguages === []) {
            return;
        }

        $existingTranslations = $this->flatTranslations()
            ->get()
            ->keyBy(static fn (FlatTranslation $translation): string => $translation->field_name.':'.$translation->language);

        $pendingUpserts = [];

        foreach (self::TRANSLATABLE_FIELDS as $fieldName) {
            $sourceText = $this->{$fieldName};

            if (! is_string($sourceText) || trim($sourceText) === '') {
                $this->flatTranslations()->where('field_name', $fieldName)->delete();
                continue;
            }

            $normalizedSourceText = trim($sourceText);
            $sourceHash = hash('sha256', $normalizedSourceText);

            foreach ($targetLanguages as $language) {
                $existingTranslation = $existingTranslations->get($fieldName.':'.$language);

                if (
                    $existingTranslation instanceof FlatTranslation
                    && $existingTranslation->source_hash === $sourceHash
                    && in_array($existingTranslation->status, [FlatTranslation::STATUS_PENDING, FlatTranslation::STATUS_READY], true)
                ) {
                    continue;
                }

                $pendingUpserts[] = [
                    'flat_id' => $this->id,
                    'field_name' => $fieldName,
                    'language' => $language,
                    'source_hash' => $sourceHash,
                    'source_text' => $normalizedSourceText,
                    'translated_text' => null,
                    'status' => FlatTranslation::STATUS_PENDING,
                    'provider' => null,
                    'failure_message' => null,
                    'translated_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($pendingUpserts !== []) {
            FlatTranslation::query()->upsert(
                $pendingUpserts,
                ['flat_id', 'field_name', 'language'],
                ['source_hash', 'source_text', 'translated_text', 'status', 'provider', 'failure_message', 'translated_at', 'updated_at'],
            );
        }
    }
}
