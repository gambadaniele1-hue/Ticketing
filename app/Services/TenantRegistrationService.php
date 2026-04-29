<?php

namespace App\Services;

use App\Exceptions\DatabaseAlreadyExistsException;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use Illuminate\Support\Str; // Ricorda di importarlo per le password casuali
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

class TenantRegistrationService
{
    public function register(array $data): Tenant
    {
        $identity = null;
        $tenant = null;

        try {
            // 1. Creiamo l'identità
            $identity = $this->createGlobalIdentity($data);

            // 2. Controllo duplicati
            if (Tenant::where('id', $data['subdomain'])->exists()) {
                throw new DatabaseAlreadyExistsException('Il subdomain "' . $data['subdomain'] . '" è già in uso.');
            }

            $plan = Plan::findOrFail($data['planId']);

            // 3. Creiamo il record del tenant (e generiamo i dati DB)
            $tenant = $this->createTenantRecord($data, $plan);

            // 4. Creiamo il dominio (ancora da fare)
            $this->createTenantDomain($tenant, $data['subdomain']);

            // 5. Colleghiamo l'identità al tenant (creando un record in tenant_memberships)
            $this->linkIdentityToTenant($identity, $tenant);

            return $tenant; // Finito!

        } catch (Throwable $e) {
            // ROLLBACK: Puliamo tutto quello che avevamo creato prima dell'errore

            // Se il tenant era stato creato, lo cancelliamo 
            // (Il pacchetto Tenancy, se configurato, cancellerà anche il DB fisico!)
            if (isset($tenant)) {
                $tenant->delete();
            }

            // Se l'identità era stata creata, la cancelliamo
            if (isset($identity)) {
                $identity->forceDelete(); // Usiamo forceDelete se hai il SoftDeletes
            }

            Log::error('Errore registrazione tenant: ' . $e->getMessage(), [
                'subdomain' => $data['subdomain'],
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function createGlobalIdentity(array $data): GlobalIdentity
    {
        return GlobalIdentity::create([
            "name" => $data["adminName"],
            "email" => $data["adminEmail"],
            "password" => Hash::make($data["adminPassword"]),
        ]);
    }

    private function createTenantRecord(array $data, Plan $plan): Tenant
    {
        $isShared = $plan->database_type === 'shared';
        $subdomain = $data['subdomain'];

        $dbName = $isShared ? env('SHARED_DB_NAME', 'ticketing_shared') : 'tenant_' . $subdomain;

        // Prepariamo l'array base (Colonne fisiche + nome DB)
        $tenantData = [
            'id' => $subdomain,
            'name' => $data['companyName'],
            'plan_id' => $plan->id,
            'tenancy_db_name' => $dbName,
        ];

        // Se è DEDICATO, generiamo e aggiungiamo le credenziali all'array 
        // (che finiranno magicamente nel JSON data).
        // Se è SHARED, non le aggiungiamo, così il pacchetto userà quelle del .env!
        if (!$isShared) {
            // usiamo una substringa per evitare errori di lunghezza con MySQL
            $tenantData['tenancy_db_username'] = 'usr_' . substr($subdomain, 0, 10);
            $tenantData['tenancy_db_password'] = Str::password(16, true, true, false);
        }

        return Tenant::create($tenantData);
    }

    private function createTenantDomain(Tenant $tenant, string $subdomain)
    {
        $baseDomain = config('tenancy.central_domains')[0] ?? env('APP_CENTRAL_DOMAIN');

        $tenant->domains()->create([
            "domain" => $subdomain . '.' . $baseDomain,
        ]);
    }

    private function linkIdentityToTenant($identity, Tenant $tenant)
    {
        $tenant->memberships()->create([
            'global_user_id' => $identity->id,
            'state' => 'accepted',
        ]);
    }

}