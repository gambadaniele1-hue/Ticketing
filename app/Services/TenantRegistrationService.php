<?php

namespace App\Services;

use App\Exceptions\DatabaseAlreadyExistsException;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\User as TenantUser;
use App\Models\Tenant\Role as TenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantRegistrationService
{
    public function register(array $data): Tenant
    {
        $plan = Plan::findOrFail($data['planId']);
        $subdomain = strtolower($data['subdomain']);
        $isShared = $plan->database_type === 'shared';
        $dbName = $isShared
            ? env('SHARED_DB_NAME', 'ticketing_shared')
            : config('tenancy.database.prefix', 'tenant_') . $subdomain;

        $globalIdentity = null;
        $tenant = null;

        if (!$isShared && $this->dedicatedDatabaseAlreadyExists($dbName)) {
            throw new DatabaseAlreadyExistsException(
                'Impossibile completare la registrazione. Il sistema ha rilevato un conflitto sul database tenant.'
            );
        }

        try {
            $globalIdentity = GlobalIdentity::create([
                'name' => $data['adminName'],
                'email' => $data['adminEmail'],
                'password' => Hash::make($data['adminPassword']),
            ]);

            $tenant = Tenant::create([
                'id' => $subdomain,
                'name' => $data['companyName'],
                'plan_id' => $plan->id,
            ]);

            $tenant->setInternal('db_name', $dbName);
            $tenant->save();

            $tenant->domains()->create([
                'domain' => $subdomain . '.' . env('APP_CENTRAL_DOMAIN', 'localhost'),
            ]);

            TenantMembership::create([
                'global_user_id' => $globalIdentity->id,
                'tenant_id' => $tenant->id,
                'state' => 'accepted',
            ]);

            $tenant->run(function () use ($globalIdentity) {
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
            Log::error('Errore creazione tenant', [
                'subdomain' => $subdomain,
                'db_name' => $dbName,
                'exception' => $e,
            ]);

            if (!$tenant) {
                $tenant = Tenant::find($subdomain);
            }

            if ($tenant) {
                try {
                    $tenant->domains()->delete();
                    TenantMembership::where('tenant_id', $tenant->id)->delete();
                    $tenant->delete();
                } catch (Throwable $cleanupError) {
                    Log::warning('Rollback tenant fallito', [
                        'tenant_id' => $tenant->id,
                        'exception' => $cleanupError,
                    ]);
                }
            }

            if ($globalIdentity) {
                try {
                    $globalIdentity->forceDelete();
                } catch (Throwable $cleanupError) {
                    Log::warning('Rollback global identity fallito', [
                        'global_identity_id' => $globalIdentity->id,
                        'exception' => $cleanupError,
                    ]);
                }
            }

            throw $e;
        }
    }

    private function dedicatedDatabaseAlreadyExists(string $dbName): bool
    {
        $connection = DB::connection(config('tenancy.database.central_connection'));
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = $connection->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$dbName]
            );

            return !empty($rows);
        }

        if ($driver === 'pgsql') {
            $rows = $connection->select(
                'SELECT datname FROM pg_database WHERE datname = ?',
                [$dbName]
            );

            return !empty($rows);
        }

        return false;
    }
}