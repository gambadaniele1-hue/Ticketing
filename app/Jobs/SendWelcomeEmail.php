<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $name,
        private readonly string $tenantName,
        private readonly string $loginUrl,
    ) {
    }

    public function handle(): void
    {
        Redis::rpush('mail:queue', json_encode([
            'to' => $this->email,
            'subject' => 'Benvenuto in ' . $this->tenantName,
            'html' => view('emails.welcome', [
                'name' => $this->name,
                'tenantName' => $this->tenantName,
                'loginUrl' => $this->loginUrl,
            ])->render(),
            'text' => "Ciao {$this->name}, benvenuto in {$this->tenantName}! Accedi qui: {$this->loginUrl}",
        ]));
    }
}