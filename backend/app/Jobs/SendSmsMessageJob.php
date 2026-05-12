<?php

namespace App\Jobs;

use App\Services\Sms\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $message,
    ) {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SmsGateway $smsGateway): void
    {
        $smsGateway->send($this->phoneNumber, $this->message);
    }

    public function messageOtpCode(): string
    {
        preg_match('/code (\d{4})/', $this->message, $matches);

        return $matches[1] ?? '';
    }
}
