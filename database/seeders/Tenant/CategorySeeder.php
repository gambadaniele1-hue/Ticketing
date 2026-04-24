<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::firstOrCreate(['name' => 'Supporto Tecnico']);
        Category::firstOrCreate(['name' => 'Fatturazione e Commerciale']);
        Category::firstOrCreate(['name' => 'Richiesta Informazioni']);
    }
}