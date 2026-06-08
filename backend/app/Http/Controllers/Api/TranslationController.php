<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Translations\LibreTranslateProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class TranslationController extends Controller
{
    public function __construct(
        private readonly LibreTranslateProxy $libreTranslateProxy,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => ['required'],
            'source' => ['nullable', 'string', 'max:16'],
            'target' => ['required', 'string', 'max:16'],
            'format' => ['nullable', 'string', Rule::in(['text', 'html'])],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $text = $request->input('q');

            if (is_string($text)) {
                return;
            }

            if (! is_array($text) || ! array_is_list($text)) {
                $validator->errors()->add('q', 'The q field must be a string or a list of strings.');

                return;
            }

            foreach ($text as $index => $value) {
                if (! is_string($value)) {
                    $validator->errors()->add("q.$index", 'Each q item must be a string.');
                }
            }
        });

        $validated = $validator->validate();

        try {
            $translatedText = $this->libreTranslateProxy->translate(
                text: $validated['q'],
                targetLanguage: $validated['target'],
                sourceLanguage: $validated['source'] ?? 'auto',
                format: $validated['format'] ?? 'text',
            );
        } catch (RuntimeException $exception) {
            Log::warning('translation.proxy.failed', [
                'target' => $validated['target'],
                'source' => $validated['source'] ?? 'auto',
                'format' => $validated['format'] ?? 'text',
                'text_count' => is_array($validated['q']) ? count($validated['q']) : 1,
                'endpoint' => config('services.libretranslate.endpoint'),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
                ...($this->shouldIncludeDebugResponse() ? [
                    'debug' => [
                        'provider' => 'libretranslate',
                        'endpoint' => config('services.libretranslate.endpoint'),
                        'target' => $validated['target'],
                        'source' => $validated['source'] ?? 'auto',
                        'format' => $validated['format'] ?? 'text',
                    ],
                ] : []),
            ], str_contains($exception->getMessage(), 'not configured')
                ? JsonResponse::HTTP_SERVICE_UNAVAILABLE
                : JsonResponse::HTTP_BAD_GATEWAY);
        }

        Log::info('translation.proxy.succeeded', [
            'target' => $validated['target'],
            'source' => $validated['source'] ?? 'auto',
            'format' => $validated['format'] ?? 'text',
            'text_count' => is_array($validated['q']) ? count($validated['q']) : 1,
            'endpoint' => config('services.libretranslate.endpoint'),
        ]);

        return response()
            ->json([
            'translatedText' => $translatedText,
                ...($this->shouldIncludeDebugResponse() ? [
                    'debug' => [
                        'provider' => 'libretranslate',
                        'endpoint' => config('services.libretranslate.endpoint'),
                        'target' => $validated['target'],
                        'source' => $validated['source'] ?? 'auto',
                        'format' => $validated['format'] ?? 'text',
                    ],
                ] : []),
            ])
            ->header('X-Translation-Proxy', 'bkp-libretranslate');
    }

    private function shouldIncludeDebugResponse(): bool
    {
        return (bool) config('services.libretranslate.debug_response', false);
    }
}
