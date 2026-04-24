<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Qui richiamiamo tutti i seeder secondari che abbiamo creato
        $this->call([
            PlanSeeder::class,
        ]);
    }
}