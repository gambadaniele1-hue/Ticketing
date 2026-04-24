<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Creazione Ruoli
        $adminRole = Role::firstOrCreate(['name' => 'Admin'], ['description' => 'Amministratore completo']);
        $agentRole = Role::firstOrCreate(['name' => 'Agent'], ['description' => 'Operatore di supporto']);
        $customerRole = Role::firstOrCreate(['name' => 'Customer'], ['description' => 'Cliente finale']);

        // Assegnazione permessi all'Admin (Tutti)
        $allPermissions = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($allPermissions);

        // Assegnazione permessi all'Agent
        $agentPermissions = Permission::whereIn('slug', [
            'tickets.view',
            'tickets.update',
            'tickets.assign',
            'messages.create',
            'messages.internal'
        ])->pluck('id')->toArray();
        $agentRole->permissions()->sync($agentPermissions);

        // Assegnazione permessi al Customer
        $customerPermissions = Permission::whereIn('slug', [
            'tickets.view',
            'tickets.create',
            'messages.create'
        ])->pluck('id')->toArray();
        $customerRole->permissions()->sync($customerPermissions);
    }
}