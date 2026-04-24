<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SlaPolicySeeder::class,
            CategorySeeder::class,
        ]);
    }
}