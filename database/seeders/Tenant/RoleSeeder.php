<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Il controllo infallibile usando i dati del Tenant stesso
        $isShared = tenant('tenancy_db_name') === env('SHARED_DB_NAME', 'ticketing_shared');
        $tenantId = tenant('id');

        // 2. Prepariamo i ruoli da inserire
        $roles = [
            ['name' => 'Admin', 'description' => 'Amministratore completo'],
            ['name' => 'Agent', 'description' => 'Operatore di supporto'],
            ['name' => 'Customer', 'description' => 'Cliente finale'],
        ];

        foreach ($roles as $roleData) {

            // LA CHIAVE DI VOLTA: Se siamo nel DB condiviso, la ricerca (e la creazione)
            // DEVONO includere il tenant_id. Altrimenti Laravel riusa quello di altri!
            $searchParams = ['name' => $roleData['name']];

            if ($isShared) {
                $searchParams['tenant_id'] = $tenantId;
                $roleData['tenant_id'] = $tenantId; // Fondamentale passarlo anche ai dati di creazione
            }

            // firstOrCreate: Cerca con i $searchParams, se non trova crea unendo $searchParams + $roleData
            $role = Role::firstOrCreate($searchParams, $roleData);

            // --- Logica dei Permessi ---
            // Recuperiamo i permessi (già precedentemente creati dal PermissionSeeder)
            $permissionQuery = Permission::query();
            if ($isShared) {
                $permissionQuery->where('tenant_id', $tenantId);
            }

            if ($role->name === 'Admin') {
                $role->permissions()->sync((clone $permissionQuery)->pluck('id')->toArray());
            } elseif ($role->name === 'Agent') {
                $role->permissions()->sync((clone $permissionQuery)->whereIn('slug', [
                    'tickets.view',
                    'tickets.update',
                    'tickets.assign',
                    'messages.create',
                    'messages.internal'
                ])->pluck('id')->toArray());
            } elseif ($role->name === 'Customer') {
                $role->permissions()->sync((clone $permissionQuery)->whereIn('slug', [
                    'tickets.view',
                    'tickets.create',
                    'messages.create'
                ])->pluck('id')->toArray());
            }
        }
    }
}