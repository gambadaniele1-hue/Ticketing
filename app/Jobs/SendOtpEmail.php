<?php

namespace App\Jobs;

use App\Models\Global\GlobalIdentity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SendOtpEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $otp,
    ) {
    }

    public function handle(): void
    {
        Log::info('[SendOtpEmail] handle chiamato per: ' . $this->email);

        Redis::rpush('mail:queue', json_encode([
            'to' => $this->email,
            'subject' => 'Il tuo codice di accesso',
            'html' => view('emails.otp', ['otp' => $this->otp])->render(),
            'text' => "Il tuo codice OTP è: {$this->otp}",
        ]));

        Log::info('[SendOtpEmail] pubblicato su Redis per: ' . $this->email);
    }
}