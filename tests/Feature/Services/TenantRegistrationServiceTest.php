<?php

namespace Tests\Feature\Services;

use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantRegistrationServiceTest extends TestCase
{
    use RefreshDatabase; // Fondamentale: pulisce il DB dopo ogni test

    public function test_it_creates_a_global_identity_during_registration()
    {
        // 1. Arrange: Prepariamo i dati
        // Creiamo un piano finto per passare la validazione (usando le colonne della tua migration)
        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared'
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme',
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password_super_sicura_123',
            'planId' => $plan->id,
        ];

        $service = app(TenantRegistrationService::class);

        // 2. Act: Eseguiamo il metodo del servizio
        try {
            $service->register($data);
        } catch (\Exception $e) {
            // Catturiamo eventuali eccezioni perché il metodo register non è ancora completo
            // e non restituirà un Tenant valido in questa fase.
        }

        // 3. Assert: Verifichiamo il database globale
        $this->assertDatabaseHas('global_identities', [
            'name' => 'Mario Rossi',
            'email' => 'mario@acme.com',
        ]);

        // Assert: Verifichiamo che la password sia stata salvata come Hash
        $identity = GlobalIdentity::where('email', 'mario@acme.com')->first();
        $this->assertNotNull($identity);
        $this->assertTrue(Hash::check('password_super_sicura_123', $identity->password));
    }
}