<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Global\Plan;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase; // Resetta il DB di test a ogni esecuzione

    public function test_shared_tenant_registration_creates_records_correctly()
    {
        // 1. Setup: Creiamo il piano Shared nel database in memoria
        $plan = Plan::create([
            'name' => 'Base',
            'price_month' => 19.90,
            'database_type' => 'shared'
        ]);

        // 2. Esecuzione: Simuliamo la chiamata API
        $payload = [
            'companyName' => 'Acme Shared',
            'subdomain' => 'acme-shared',
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acmeshared.com',
            'adminPassword' => 'PasswordSicura123!',
            'planId' => $plan->id,
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        // 3. Asserzioni API: Verifichiamo il 201
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'tenant' => ['id', 'name']
                 ]);

        // 4. Asserzioni DB Globale: Verifichiamo che il tenant punti al DB condiviso
        $this->assertDatabaseHas('tenants', [
            'id' => 'acme-shared',
            'db_name' => env('SHARED_DB_NAME', 'ticketing_shared')
        ]);

        // Verifichiamo che l'identità globale sia stata creata
        $this->assertDatabaseHas('global_identities', [
            'email' => 'mario@acmeshared.com'
        ]);
        
        // (Nota: Per testare a fondo il DB tenant in PHPUnit serve una configurazione
        // aggiuntiva di Tenancy, ma testare il Global DB è già un'ottima copertura base).
    }
}