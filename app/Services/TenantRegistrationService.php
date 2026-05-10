<?php

namespace App\Services;

use App\Jobs\SendOtpEmail;
use App\Models\Global\OtpCode;
use App\Exceptions\DatabaseAlreadyExistsException;
use App\Jobs\CreateTenantAdminUser;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // IMPORTANTE
use Illuminate\Validation\ValidationException; // IMPORTANTE
use Stancl\Tenancy\Database\Models\Domain;

class TenantRegistrationService
{
    private string $lastOtp; // ← proprietà per passare l'OTP fuori dalla funzione
    public function register(array $data): Tenant
    {
        $plan = Plan::findOrFail($data['planId']);

        $this->runPreflightChecks($data['subdomain'], $plan);

        // FLUSSO UNIFICATO: Niente transazioni automatiche.
        // Usiamo il nostro try/catch blindato per tutti i piani.
        try {
            $tenant = $this->executeRegistration($data, $plan);

            // --- AGGIUNGI QUESTA RIGA ALLA FINE ---
            // Ora che la membership ESISTE SICURAMENTE, lanciamo il Job!
            CreateTenantAdminUser::dispatch($tenant)
                ->delay(now()->addSeconds(2))
                ->afterCommit();
            // (Usiamo dispatchSync così in fase di registrazione l'utente viene creato subito prima di rispondere al frontend)

            // Dispatchiamo il job email DOPO che tutto è andato bene
            SendOtpEmail::dispatch($data['adminEmail'], $this->lastOtp);

            return $tenant;
        } catch (Exception $e) {
            // Passiamo anche il $plan al rollback per fargli capire cosa pulire
            $this->manualRollback($data['subdomain'], $data['adminEmail'], $plan);
            throw $e;
        }
    }

    /**
     * Il cuore della registrazione. Adesso non sa nulla di transazioni.
     */
    private function executeRegistration(array $data, Plan $plan): Tenant
    {
        $identity = $this->createGlobalIdentity($data);
        $tenant = $this->createTenantRecord($data, $plan);
        $this->createTenantDomain($tenant, $data['subdomain']);
        $this->linkIdentityToTenant($identity, $tenant);

        // Genera e salva OTP
        $this->lastOtp = $this->createOtp($identity);

        return $tenant;
    }

    /**
     * Cerca di eliminare i record creati se la registrazione dedicata fallisce.
     */
    private function manualRollback(string $subdomain, string $email, Plan $plan): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // 1. Pulizia Tenant e dati correlati
        try {
            $tenant = Tenant::find($subdomain);
            if ($tenant) {
                // NOVITÀ: Se il piano è shared, svuotiamo i dati del seeder prima di eliminare il tenant
                if ($plan->database_type === 'shared') {
                    $this->cleanupSharedSeederData($tenant);
                }

                $tenant->delete();
            }
        } catch (Exception $e) {
            Log::warning("Impossibile eliminare il tenant nel rollback: " . $e->getMessage());
        }

        // 2. Pulizia Identità
        try {
            $identity = GlobalIdentity::where('email', $email)->first();
            if ($identity) {
                $identity->forceDelete(); // Usiamo forceDelete per scavalcare eventuali SoftDeletes!
            }
        } catch (Exception $e) {
            Log::warning("Impossibile eliminare l'identità nel rollback: " . $e->getMessage());
        }
    }

    /**
     * Pulisce i record rimasti orfani nel database condiviso.
     */
    private function cleanupSharedSeederData(Tenant $tenant): void
    {
        try {
            // Inizializziamo il tenant per puntare alla connessione corretta
            tenancy()->initialize($tenant);

            // Opzione A: Se usi i Model che usano il trait "BelongsToTenant" di Stancl, 
            // il tenant_id viene aggiunto automaticamente alla query.
            // \App\Models\Category::query()->delete();

            // Opzione B (Più sicura se non sei certo dei Model): Usa le query grezze
            // dicendo esplicitamente di cancellare SOLO i dati di questo tenant!
            DB::connection('tenant')->table('categories')->where('tenant_id', $tenant->id)->delete();
            DB::connection('tenant')->table('permissions')->where('tenant_id', $tenant->id)->delete();
            DB::connection('tenant')->table('permission_role')->where('tenant_id', $tenant->id)->delete();
            DB::connection('tenant')->table('roles')->where('tenant_id', $tenant->id)->delete();
            DB::connection('tenant')->table('sla_polices')->where('tenant_id', $tenant->id)->delete();

            DB::connection('tenant')->table('users')->where('tenant_id', $tenant->id)->delete();
            DB::connection('otp_codes')->table('users')->where('tenant_id', $tenant->id)->delete();

            // Aggiungi qui altre tabelle riempite dal tuo Seeder
            // DB::connection('tenant')->table('products')->where('tenant_id', $tenant->id)->delete();
            // DB::connection('tenant')->table('orders')->where('tenant_id', $tenant->id)->delete();

        } catch (Exception $e) {
            Log::error("Fallita la pulizia dei dati shared per il tenant {$tenant->id}: " . $e->getMessage());
        } finally {
            // Usciamo sempre dal tenant alla fine!
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
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
                throw new DatabaseAlreadyExistsException;
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

    private function createOtp(GlobalIdentity $identity): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'global_identity_id' => $identity->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }
}