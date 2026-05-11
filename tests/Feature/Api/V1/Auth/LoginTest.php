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
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;
    protected GlobalIdentity $user;
    protected Tenant $tenant;
    protected string $email;
    protected string $password;
    protected string $tenantDomain;

    private function loginUrlForDomain(string $domain): string
    {
        return 'http://' . $domain . '/api/v1/auth/login';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushDB();

        $this->password = 'I-am-batman-123';
        $this->email = 'bruce+' . Str::lower(Str::random(8)) . '@wayne.com';

        // Creiamo il piano condiviso per Bruce Wayne
        $this->plan = Plan::create([
            'name' => 'Test Plan Shared',
            'price_month' => 9.99,
            'database_type' => 'shared',
        ]);

        $registrationData = [
            'companyName' => 'Wayne Enterprises',
            'subdomain' => 'wayne-enterprises-' . Str::lower(Str::random(6)),
            'adminName' => 'Bruce Wayne',
            'adminEmail' => $this->email,
            'adminPassword' => $this->password,
            'planId' => $this->plan->id,
        ];

        // 1. Lasciamo fare tutto al Service in modo NATURALE
        // Questo lancerà anche il Job che creerà il Ruolo "Admin" e lo User nel DB shared!
        $this->tenant = app(TenantRegistrationService::class)->register($registrationData);

        $this->user = GlobalIdentity::where('email', $this->email)->firstOrFail();
        $this->tenantDomain = $this->tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        // 1. Usciamo preventivamente da qualsiasi inizializzazione rimasta appesa
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if (isset($this->tenant)) {
            // Verifichiamo il tipo di piano (puoi usare anche il nome se preferisci)
            // Assicurati che 'database_type' sia caricato o disponibile
            $isShared = $this->tenant->plan->database_type === 'shared';
            // In alternativa, come volevi fare tu: 
            // $isShared = !str_contains(strtolower($this->tenant->plan->name), 'dedicated');

            if ($isShared) {
                // --- PULIZIA PROFONDA PIANO SHARED ---
                tenancy()->initialize($this->tenant);

                // Puliamo tutte le tabelle che usano il tenant_id
                User::where('tenant_id', $this->tenant->id)->forceDelete();
                Role::where('tenant_id', $this->tenant->id)->delete();
                Permission::where('tenant_id', $this->tenant->id)->delete();
                SlaPolicy::where('tenant_id', $this->tenant->id)->delete();
                Category::where('tenant_id', $this->tenant->id)->delete();

                tenancy()->end();
            }

            // --- ELIMINAZIONE DEL TENANT (E DEL DB FISICO SE DEDICATO) ---
            // Se è dedicated, il pacchetto Stancl cancellerà il DB intero tramite l'evento TenantDeleted.
            // Se è shared, cancellerà solo la riga dalla tabella 'tenants' e 'domains'.
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    // ==========================================
    // I TEST DEL LOGIN
    // ==========================================

    public function test_user_can_login_with_valid_credentials()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'tenant' => ['id', 'name', 'plan_id']
            ]
        ]);

        $response->assertCookie('access_token');
        $response->assertCookie('refresh_token');
    }

    public function test_login_fails_with_wrong_password()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), [
                'email' => $this->email,
                'password' => 'password-sbagliata',
            ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Credenziali non valide']);
        $response->assertCookieMissing('access_token');
    }

    public function test_login_requires_validation()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_fails_if_user_does_not_belong_to_tenant()
    {
        // NON CREIAMO PIÙ IL PIANO DEDICATO. 
        // Usiamo il piano Shared di Bruce Wayne ($this->plan), ora che i permessi non vanno in conflitto!

        $otherTenant = app(TenantRegistrationService::class)->register([
            'companyName' => 'Stark Industries',
            'subdomain' => 'stark-industries-' . Str::lower(Str::random(6)),
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony+' . Str::lower(Str::random(6)) . '@stark.com',
            'adminPassword' => 'IamIronman-123',
            'planId' => $this->plan->id, // <--- USIAMO IL PIANO CONDIVISO!
        ]);

        $otherTenantDomain = $otherTenant->domains()->value('domain');

        // Bruce Wayne tenta di loggarsi nel tenant di Tony Stark
        $response = $this->withServerVariables(['HTTP_HOST' => $otherTenantDomain])
            ->postJson($this->loginUrlForDomain($otherTenantDomain), [
                'email' => $this->email,
                'password' => $this->password, // Password giusta, ma dominio sbagliato
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Non hai accesso a questa area di lavoro.']);

        // Puliamo i record di Tony Stark dal DB shared prima di eliminare il tenant
        tenancy()->initialize($otherTenant);
        User::where('tenant_id', $otherTenant->id)->forceDelete();
        Role::where('tenant_id', $otherTenant->id)->delete();
        Permission::where('tenant_id', $otherTenant->id)->delete();
        SlaPolicy::where('tenant_id', $otherTenant->id)->delete();
        Category::where('tenant_id', $otherTenant->id)->delete();
        tenancy()->end();

        $otherTenant->delete();
    }

    public function test_login_fails_if_membership_is_not_accepted()
    {
        // Sospendiamo l'utente modificando lo stato tramite query pura
        $this->user->memberships()->where('tenant_id', $this->tenant->id)->update(['state' => 'pending']);

        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Non hai accesso a questa area di lavoro.']);
    }

    public function test_login_fails_if_local_profile_is_missing()
    {
        // Simuliamo un disallineamento: cancelliamo l'utente locale dal DB del tenant appena prima del login
        tenancy()->initialize($this->tenant);
        User::where('global_user_id', $this->user->id)->delete();
        tenancy()->end();

        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        // Verifichiamo il ritorno della 500 dal controller
        $response->assertStatus(500);
        $response->assertJson(['message' => 'Profilo locale non trovato.']);
    }
}