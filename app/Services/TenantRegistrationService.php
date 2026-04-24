<?php

namespace App\Services;

use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\User as TenantUser;
use App\Models\Tenant\Role as TenantRole;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantRegistrationService
{
    public function register(array $data): Tenant
    {
        $requiredFields = ['planId', 'subdomain', 'companyName', 'adminName', 'adminEmail', 'adminPassword'];
        if (!Arr::has($data, $requiredFields)) {
            throw new InvalidArgumentException('Missing required registration fields.');
        }

        // 1. Validazione iniziale (se fallisce, si ferma subito senza sporcare nulla)
        $plan = Plan::findOrFail($data['planId']);
        $subdomain = strtolower($data['subdomain']);
        $isShared = $plan->database_type === 'shared';
        $dbName = $isShared ? env('SHARED_DB_NAME', 'ticketing_shared') : 'tenant_' . $subdomain;

        // Inizializziamo a null per il rollback manuale
        $globalIdentity = null;
        $tenant = null;

        try {
            // NESSUNA DB::transaction! Facciamo le query dirette.
            $globalIdentity = GlobalIdentity::create([
                'name' => $data['adminName'],
                'email' => $data['adminEmail'],
                'password' => Hash::make($data['adminPassword']),
            ]);

            // Questo innesca la Pipeline di Tenancy (Creazione DB, Migrazioni, Seeder)
            $tenant = Tenant::create([
                'id' => $subdomain,
                'name' => $data['companyName'],
                'plan_id' => $plan->id,
                'db_name' => $dbName,
            ]);

            $tenant->domains()->create([
                'domain' => $subdomain . '.' . env('APP_CENTRAL_DOMAIN', 'localhost'),
            ]);

            TenantMembership::create([
                'global_user_id' => $globalIdentity->id,
                'tenant_id' => $tenant->id,
                'state' => 'accepted',
            ]);

            // Lo switch di contesto ora funzionerà perfettamente
            $tenant->run(function () use ($globalIdentity) {
                DB::purge();

                $adminRole = TenantRole::firstOrCreate([
                    'name' => 'Admin',
                ], [
                    'description' => 'Amministratore completo',
                ]);

                TenantUser::create([
                    'global_user_id' => $globalIdentity->id,
                    'role_id' => $adminRole->id,
                ]);
            });

            return $tenant;

        } catch (Throwable $e) {
            // ROLLBACK MANUALE (SAGA PATTERN)
            Log::error('Errore Creazione Tenant: ' . $e->getMessage());

            // Cancelliamo tutto in ordine inverso se le entità sono state create
            if ($tenant) {
                try {
                    // Questo droppa anche il DB fisico se Tenancy è configurato bene
                    $tenant->delete();
                } catch (Throwable $cleanupError) {
                    Log::warning('Rollback tenant fallito: ' . $cleanupError->getMessage());
                }
            }
            if ($globalIdentity) {
                try {
                    $globalIdentity->forceDelete();
                } catch (Throwable $cleanupError) {
                    Log::warning('Rollback global identity fallito: ' . $cleanupError->getMessage());
                }
            }

            throw $e;
        }
    }
}