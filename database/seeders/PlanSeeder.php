<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Global\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Piano Base -> Userà il Database Condiviso (Shared)
        Plan::updateOrCreate(
            ['name' => 'Base'],
            [
                'description' => 'Ideale per startup. Database condiviso, isolamento logico.',
                'price_month' => 19.90,
                'database_type' => 'shared'
            ]
        );

        // Piano Enterprise -> Genererà un Database Dedicato
        Plan::updateOrCreate(
            ['name' => 'Enterprise'],
            [
                'description' => 'Per grandi aziende. Database fisico dedicato e performance isolate.',
                'price_month' => 99.00,
                'database_type' => 'dedicated'
            ]
        );
    }
}