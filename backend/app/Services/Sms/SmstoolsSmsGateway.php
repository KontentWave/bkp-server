<?php

namespace App\Services\Sms;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SmstoolsSmsGateway implements SmsGateway
{
    public function send(string $phoneNumber, string $message): void
    {
        $apiKey = trim((string) config('services.smstools.api_key', ''));

        if ($apiKey === '') {
            throw new SmsGatewayException('SMSTools API key is not configured.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.smstools.base_url', 'https://api.smstools.sk'), '/'))
            ->connectTimeout((int) config('services.smstools.connect_timeout', 10))
            ->timeout((int) config('services.smstools.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->post('/3/send_batch', [
                'auth' => [
                    'apikey' => $apiKey,
                ],
                'data' => [
                    'message' => $message,
                    'simple_text' => (bool) config('services.smstools.simple_text', false),
                    'sender' => $this->buildSender(),
                    'recipients' => [
                        ['phonenr' => $phoneNumber],
                    ],
                ],
            ]);

        $response->throw();

        if ($response->json('id') === 'OK') {
            return;
        }

        $statusId = (string) $response->json('id', 'UNKNOWN_ERROR');
        $note = trim((string) $response->json('note', ''));

        throw new SmsGatewayException($note !== ''
            ? sprintf('SMSTools rejected the SMS request [%s]: %s', $statusId, $note)
            : sprintf('SMSTools rejected the SMS request [%s].', $statusId));
    }

    /**
     * @return array{text?: string, phonenr?: string}
     */
    private function buildSender(): array
    {
        $sender = [
            'text' => trim((string) config('services.smstools.sender_text', 'BKP')),
            'phonenr' => trim((string) config('services.smstools.sender_phone', '')),
        ];

        $sender = Arr::where($sender, static fn (string $value): bool => $value !== '');

        if ($sender === []) {
            return ['text' => 'BKP'];
        }

        return $sender;
    }
}
