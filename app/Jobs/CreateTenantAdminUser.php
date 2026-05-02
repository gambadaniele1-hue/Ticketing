<?php

namespace App\Jobs;

use App\Models\Tenant\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTenantAdminUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    /**
     * Il Tenant viene iniettato dalla JobPipeline nel costruttore.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        // 1. Troviamo la Membership nel DB centrale per capire chi è l'admin
        $membership = TenantMembership::where('tenant_id', $this->tenant->id)->first();

        if (!$membership) {
            Log::error("Impossibile creare admin per tenant {$this->tenant->id}: Membership non trovata.");
            return;
        }

        // 2. Entriamo nel contesto del Tenant (che sia fisico o shared, fa tutto Stancl)
        $this->tenant->run(function () use ($membership) {

            // Dati base per la tabella users
            $userData = [
                'global_user_id' => $membership->global_user_id,
            ];

            // 3. LA TUA LOGICA: Se siamo nel database condiviso, aggiungiamo il tenant_id
            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $userData['tenant_id'] = $this->tenant->id;
            }

            $adminRole = Role::where('name', 'Admin')->first();

            if (!$adminRole) {
                Log::warning("Ruolo 'Admin' non trovato nel tenant {$this->tenant->id}. L'utente è stato creato senza ruolo.");
            }

            $userData['role_id'] = $adminRole->id;
            // 4. Creiamo l'utente
            User::create($userData);

            Log::info("Utente admin (Global ID: {$membership->global_user_id}) creato nel tenant {$this->tenant->id}");
        });
    }
}