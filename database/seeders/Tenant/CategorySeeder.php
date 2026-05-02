<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $isShared = tenant('tenancy_db_name') === env('SHARED_DB_NAME', 'ticketing_shared');
        $tenantId = tenant('id');

        $categories = ['Supporto Tecnico', 'Fatturazione e Commerciale', 'Richiesta Informazioni'];

        foreach ($categories as $name) {
            $data = ['name' => $name];

            if ($isShared) {
                $data['tenant_id'] = $tenantId;
            }

            // Dato che qui "name" e "tenant_id" sono entrambi sia parametri di ricerca 
            // che di inserimento, basta un solo array in firstOrCreate()
            Category::firstOrCreate($data);
        }
    }
}