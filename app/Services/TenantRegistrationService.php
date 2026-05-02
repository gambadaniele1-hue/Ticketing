<?php

namespace App\Services;

use App\Exceptions\DatabaseAlreadyExistsException;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // IMPORTANTE
use Illuminate\Validation\ValidationException; // IMPORTANTE
use Stancl\Tenancy\Database\Models\Domain;

class TenantRegistrationService
{
    public function register(array $data): Tenant
    {
        $plan = Plan::findOrFail($data['planId']);

        // --- 1. PRE-FLIGHT CHECKS (Il nostro scudo) ---
        // Rimane fuori dalla transazione per non bloccare inutilmente il DB 
        // mentre interroghiamo INFORMATION_SCHEMA
        $this->runPreflightChecks($data['subdomain'], $plan);

        // --- 2. TRANSAZIONE SUL DATABASE CENTRALE ---
        // Se una qualsiasi cosa fallisce qui dentro, Laravel annulla le query precedenti in automatico.
        return DB::transaction(function () use ($data, $plan) {

            // 1. Creiamo l'identità
            $identity = $this->createGlobalIdentity($data);

            // 2. Controllo duplicati (con blocco in scrittura per sicurezza estrema)
            if (Tenant::where('id', $data['subdomain'])->lockForUpdate()->exists()) {
                throw new DatabaseAlreadyExistsException('Il subdomain "' . $data['subdomain'] . '" è già in uso.');
            }

            // 3. Creiamo il record del tenant (e generiamo i dati DB)
            $tenant = $this->createTenantRecord($data, $plan);

            // 4. Creiamo il dominio
            $this->createTenantDomain($tenant, $data['subdomain']);

            // 5. Colleghiamo l'identità al tenant
            $this->linkIdentityToTenant($identity, $tenant);

            return $tenant; // Finito!
        });
    }

    /**
     * Esegue i controlli infrastrutturali per prevenire fallimenti nei Job in coda.
     */
    private function runPreflightChecks(string $subdomain, Plan $plan): void
    {
        // 1. Controllo Dominio: Stancl/Tenancy usa una tabella separata 'domains'. 
        $domainExists = DB::table('domains')->where('domain', $subdomain)->exists();
        if ($domainExists) {
            throw ValidationException::withMessages([
                'subdomain' => 'Questo sottodominio è già in uso.'
            ]);
        }

        // 2. Controlli Infrastrutturali (SOLO per piani Dedicati)
        if ($plan->database_type === 'dedicated') {
            $dbName = 'tenant_' . $subdomain;

            // A. Esiste già il database fisico su MySQL?
            $databaseExists = DB::select(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                [$dbName]
            );

            if (!empty($databaseExists)) {
                throw ValidationException::withMessages([
                    'subdomain' => "Errore di sistema: Il database fisico '{$dbName}' è già presente sul server. Scegli un altro nome o contatta l'assistenza."
                ]);
            }
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
        $randomSuffix = strtolower(Str::random(4));

        $dbName = $isShared ? env('SHARED_DB_NAME', 'ticketing_shared') : 'tenant_' . $subdomain;

        $tenantData = [
            'id' => $subdomain,
            'name' => $data['companyName'],
            'plan_id' => $plan->id,
            'tenancy_db_name' => $dbName,
        ];

        if (!$isShared) {
            $tenantData['tenancy_db_username'] = 'usr_' . substr($subdomain, 0, 10) . "_" . $randomSuffix;
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