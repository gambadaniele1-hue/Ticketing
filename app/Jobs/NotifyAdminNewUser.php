<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class NotifyAdminNewUser implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $adminEmail,
        private readonly string $adminName,
        private readonly string $newUserName,
        private readonly string $newUserEmail,
        private readonly string $tenantName,
        private readonly string $loginFrontendURL,
        private readonly string $requestedAt,
    ) {
    }

    public function handle(): void
    {
        Redis::rpush('mail:queue', json_encode([
            'to' => $this->adminEmail,
            'subject' => 'Nuova richiesta di accesso a ' . $this->tenantName,
            'html' => view('emails.notify-admin-new-user', [
                'adminName' => $this->adminName,
                'newUserName' => $this->newUserName,
                'newUserEmail' => $this->newUserEmail,
                'tenantName' => $this->tenantName,
                'loginFrontendURL' => $this->loginFrontendURL,
                'requestedAt' => $this->requestedAt,
            ])->render(),
            'text' => "Ciao {$this->adminName}, l'utente {$this->newUserName} ({$this->newUserEmail}) vuole accedere a {$this->tenantName}. Accedi qui: {$this->loginFrontendURL}",
        ]));
    }
}
