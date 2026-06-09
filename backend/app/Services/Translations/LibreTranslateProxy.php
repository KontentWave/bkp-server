<?php

namespace App\Services\Translations;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class LibreTranslateProxy
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param  string|array<int, string>  $text
     * @return string|array<int, string>
     */
    public function translate(string|array $text, string $targetLanguage, string $sourceLanguage = 'auto', string $format = 'text'): string|array
    {
        $endpoint = trim((string) config('services.libretranslate.endpoint', ''));

        if ($endpoint === '') {
            throw new RuntimeException('LibreTranslate endpoint is not configured.');
        }

        $texts = is_array($text) ? array_values($text) : [$text];
        $requestText = is_array($text) ? $texts : $texts[0];
        $apiKey = trim((string) config('services.libretranslate.api_key', ''));
        $connectTimeout = (int) config('services.libretranslate.connect_timeout', 5);
        $timeout = (int) config('services.libretranslate.timeout', 20);

        $response = $this->http
            ->acceptJson()
            ->asForm()
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->post($endpoint, [
                'q' => $requestText,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
                'format' => $format,
                ...($apiKey !== '' ? ['api_key' => $apiKey] : []),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('LibreTranslate request failed with status '.$response->status().'.');
        }

        $responseJson = $response->json();
        $translated = $this->extractTranslatedTexts($responseJson, $texts);

        return is_array($text)
            ? $translated
            : ($translated[0] ?? $texts[0]);
    }

    /**
     * @param  mixed  $responseJson
     * @param  array<int, string>  $originalTexts
     * @return array<int, string>
     */
    private function extractTranslatedTexts(mixed $responseJson, array $originalTexts): array
    {
        if (is_array($responseJson) && array_is_list($responseJson)) {
            return array_map(
                static function (string $originalText, int $index) use ($responseJson): string {
                    $item = $responseJson[$index] ?? null;

                    if (is_array($item) && is_string($item['translatedText'] ?? null) && trim($item['translatedText']) !== '') {
                        return $item['translatedText'];
                    }

                    return $originalText;
                },
                $originalTexts,
                array_keys($originalTexts),
            );
        }

        if (is_array($responseJson) && array_key_exists('translatedText', $responseJson)) {
            $translatedText = $responseJson['translatedText'];

            if (is_array($translatedText) && array_is_list($translatedText)) {
                return array_map(
                    static function (string $originalText, int $index) use ($translatedText): string {
                        $value = $translatedText[$index] ?? null;

                        return is_string($value) && trim($value) !== ''
                            ? $value
                            : $originalText;
                    },
                    $originalTexts,
                    array_keys($originalTexts),
                );
            }

            if (is_string($translatedText) && trim($translatedText) !== '') {
                return [$translatedText];
            }
        }

        return $originalTexts;
    }
}
