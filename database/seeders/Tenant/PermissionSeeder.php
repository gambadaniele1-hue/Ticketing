<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Tickets
            ['slug' => 'tickets.view', 'description' => 'Visualizzare i ticket'],
            ['slug' => 'tickets.create', 'description' => 'Creare nuovi ticket'],
            ['slug' => 'tickets.update', 'description' => 'Aggiornare i ticket'],
            ['slug' => 'tickets.delete', 'description' => 'Eliminare i ticket'],
            ['slug' => 'tickets.assign', 'description' => 'Assegnare ticket agli operatori'],
            // Messages
            ['slug' => 'messages.create', 'description' => 'Rispondere ai ticket'],
            ['slug' => 'messages.internal', 'description' => 'Inviare note interne'],
            // Settings
            ['slug' => 'settings.manage', 'description' => 'Gestire impostazioni tenant'],
            ['slug' => 'users.manage', 'description' => 'Gestire utenti e ruoli'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }
    }
}