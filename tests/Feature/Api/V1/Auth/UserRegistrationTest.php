<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Tenant\Category;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\SlaPolicy;
use App\Models\Tenant\User;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;
    protected Tenant $tenant;
    protected string $tenantDomain;
    protected string $adminEmail;

    private function registerUrl(): string
    {
        return 'http://' . $this->tenantDomain . '/api/v1/auth/register';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushDB();

        $this->adminEmail = 'admin+' . Str::lower(Str::random(8)) . '@acme.com';

        $this->plan = Plan::create([
            'name' => 'Test Plan Shared',
            'price_month' => 9.99,
            'database_type' => 'shared',
        ]);

        $this->tenant = app(TenantRegistrationService::class)->register([
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme-reg-' . Str::lower(Str::random(6)),
            'adminName' => 'Admin Acme',
            'adminEmail' => $this->adminEmail,
            'adminPassword' => 'Secret-1234',
            'planId' => $this->plan->id,
        ]);

        $this->tenantDomain = $this->tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if (isset($this->tenant)) {
            if ($this->tenant->plan->database_type === 'shared') {
                tenancy()->initialize($this->tenant);

                User::where('tenant_id', $this->tenant->id)->forceDelete();
                Role::where('tenant_id', $this->tenant->id)->delete();
                Permission::where('tenant_id', $this->tenant->id)->delete();
                SlaPolicy::where('tenant_id', $this->tenant->id)->delete();
                Category::where('tenant_id', $this->tenant->id)->delete();

                tenancy()->end();
            }

            $this->tenant->delete();
        }

        parent::tearDown();
    }

    // ==========================================
    // I TEST DELLA REGISTRAZIONE UTENTE
    // ==========================================

    public function test_user_can_register_with_new_email(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Mario Rossi',
                'email' => 'mario.rossi@example.com',
                'password' => 'Password-123',
            ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Registrazione completata. Sei in attesa di approvazione.']);

        // Viene creata la global_identity (connessione centrale esplicita perché la tenancy è ancora attiva)
        $this->assertDatabaseHas('global_identities', [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
        ], 'mysql');

        $identity = GlobalIdentity::where('email', 'mario.rossi@example.com')->firstOrFail();

        // La password è hashata correttamente
        $this->assertTrue(Hash::check('Password-123', $identity->password));

        // Viene creata la membership con stato pending
        $this->assertDatabaseHas('tenant_memberships', [
            'global_user_id' => $identity->id,
            'tenant_id' => $this->tenant->id,
            'state' => 'pending',
        ], 'mysql');
    }

    public function test_user_with_existing_global_identity_gets_only_membership_created(): void
    {
        // Creiamo un'identità globale già esistente (ad es. proveniente da un altro tenant)
        $existingIdentity = GlobalIdentity::create([
            'name' => 'Luigi Verdi',
            'email' => 'luigi.verdi@example.com',
            'password' => Hash::make('OldPassword-123'),
        ]);

        $identityCountBefore = GlobalIdentity::where('email', 'luigi.verdi@example.com')->count();

        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Luigi Verdi',
                'email' => 'luigi.verdi@example.com',
                'password' => 'NewPassword-123',
            ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Registrazione completata. Sei in attesa di approvazione.']);

        // Nessuna nuova global_identity deve essere creata
        $this->assertEquals(
            $identityCountBefore,
            GlobalIdentity::where('email', 'luigi.verdi@example.com')->count(),
            'Non deve essere creata una nuova global_identity per un\'email già esistente'
        );

        // La password dell'identità esistente NON deve essere stata modificata
        $existingIdentity->refresh();
        $this->assertTrue(Hash::check('OldPassword-123', $existingIdentity->password));
        $this->assertFalse(Hash::check('NewPassword-123', $existingIdentity->password));

        // La membership pending viene creata correttamente
        $this->assertDatabaseHas('tenant_memberships', [
            'global_user_id' => $existingIdentity->id,
            'tenant_id' => $this->tenant->id,
            'state' => 'pending',
        ], 'mysql');
    }

    public function test_registration_fails_if_email_is_already_accepted_in_this_tenant(): void
    {
        // L'adminEmail è già registrata in questo tenant con stato 'accepted' (creata nel setUp)
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Admin Clone',
                'email' => $this->adminEmail,
                'password' => 'Password-123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_if_email_is_already_pending_in_this_tenant(): void
    {
        // Prima registrazione: crea global_identity e membership pending
        $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Anna Bianchi',
                'email' => 'anna.bianchi@example.com',
                'password' => 'Password-123',
            ]);

        // Seconda registrazione con la stessa email: deve restituire errore
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Anna Bianchi',
                'email' => 'anna.bianchi@example.com',
                'password' => 'Password-456',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_all_fields(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_fails_with_invalid_email_format(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Mario Rossi',
                'email' => 'non-un-email-valida',
                'password' => 'Password-123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_if_password_is_too_short(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->registerUrl(), [
                'name' => 'Mario Rossi',
                'email' => 'mario.rossi@example.com',
                'password' => 'corta',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
