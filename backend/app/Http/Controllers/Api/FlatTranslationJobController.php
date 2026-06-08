<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlatTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlatTranslationJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? config('services.flat_translation.job_batch_size', 20));

        $jobs = FlatTranslation::query()
            ->where('status', FlatTranslation::STATUS_PENDING)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $jobs->map(fn (FlatTranslation $job): array => [
                'id' => $job->id,
                'flat_id' => $job->flat_id,
                'field_name' => $job->field_name,
                'language' => $job->language,
                'source_text' => $job->source_text,
                'source_hash' => $job->source_hash,
                'status' => $job->status,
                'updated_at' => $job->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(Request $request, FlatTranslation $flatTranslation): JsonResponse
    {
        $validated = $request->validate([
            'source_hash' => ['required', 'string', 'size:64'],
            'status' => ['required', Rule::in([
                FlatTranslation::STATUS_READY,
                FlatTranslation::STATUS_FAILED,
            ])],
            'translated_text' => ['required_if:status,'.FlatTranslation::STATUS_READY, 'nullable', 'string'],
            'failure_message' => ['nullable', 'string', 'max:500'],
            'provider' => ['nullable', 'string', 'max:120'],
        ]);

        if (! hash_equals($flatTranslation->source_hash, $validated['source_hash'])) {
            return response()->json([
                'message' => 'Translation job source hash is stale.',
                'current_source_hash' => $flatTranslation->source_hash,
            ], JsonResponse::HTTP_CONFLICT);
        }

        $status = $validated['status'];

        $flatTranslation->update([
            'status' => $status,
            'translated_text' => $status === FlatTranslation::STATUS_READY
                ? $validated['translated_text']
                : null,
            'provider' => $validated['provider'] ?? $flatTranslation->provider,
            'failure_message' => $status === FlatTranslation::STATUS_FAILED
                ? ($validated['failure_message'] ?? 'Translation failed.')
                : null,
            'translated_at' => $status === FlatTranslation::STATUS_READY ? now() : null,
        ]);

        return response()->json([
            'data' => [
                'id' => $flatTranslation->id,
                'status' => $flatTranslation->status,
                'translated_at' => $flatTranslation->translated_at?->toIso8601String(),
            ],
        ]);
    }
}
