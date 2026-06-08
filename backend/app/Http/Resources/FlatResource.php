<?php

namespace App\Http\Resources;

use App\Models\Flat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Flat
 *
 * @property bool|null $my_vote
 * @property bool|null $is_owned_by_viewer
 * @property int|null $landlord_reports_count
 * @property array<int, string> $landlord_report_reasons
 * @property array<int, string> $my_landlord_report_reasons
 */
class FlatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'landlord_id' => $this->landlord_id,
            'municipality_id' => $this->municipality_id,
            'title' => $this->title,
            'description' => $this->description,
            'city' => $this->municipality?->name,
            'district' => $this->municipality?->district,
            'region' => $this->municipality?->region,
            'translations' => $this->formatTranslations(),
            'contact' => [
                'phone' => $this->contact_phone,
                'email' => $this->contact_email,
                'whatsapp_url' => $this->whatsapp_url,
                'telegram_url' => $this->telegram_url,
                'viber_url' => $this->viber_url,
            ],
            'photos' => FlatPhotoResource::collection($this->whenLoaded('photos')),
            'votes_count' => $this->whenCounted('votes', fn (): int => (int) $this->votes_count),
            'my_vote' => $this->my_vote,
            'is_owned_by_viewer' => (bool) ($this->is_owned_by_viewer ?? false),
            'landlord_reports_count' => (int) ($this->landlord_reports_count ?? 0),
            'landlord_report_reasons' => $this->landlord_report_reasons ?? [],
            'my_landlord_report_reasons' => $this->my_landlord_report_reasons ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function formatTranslations(): array
    {
        if (! $this->relationLoaded('flatTranslations')) {
            return [];
        }

        $translations = [];

        foreach ($this->flatTranslations as $translation) {
            if (
                ! is_string($translation->translated_text)
                || trim($translation->translated_text) === ''
                || $translation->status !== \App\Models\FlatTranslation::STATUS_READY
            ) {
                continue;
            }

            $sourceText = match ($translation->field_name) {
                'title' => $this->title,
                'description' => $this->description,
                default => null,
            };

            if (! is_string($sourceText) || trim($sourceText) === '') {
                continue;
            }

            if (! hash_equals(hash('sha256', trim($sourceText)), $translation->source_hash)) {
                continue;
            }

            $translations[$translation->language][$translation->field_name] = $translation->translated_text;
        }

        return $translations;
    }
}
