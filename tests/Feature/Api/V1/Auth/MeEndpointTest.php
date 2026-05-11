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
use App\Services\JwtService;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;
    protected GlobalIdentity $user;
    protected Tenant $tenant;
    protected string $tenantDomain;
    protected User $localUser; // Aggiungiamo il localUser per comodità

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushDB();

        // Prepariamo i dati standard
        $password = 'I-am-batman-123';
        $email = 'bruce+' . Str::lower(Str::random(8)) . '@wayne.com';

        $this->plan = Plan::create(['name' => 'Shared Plan', 'database_type' => 'shared', 'price_month' => 9.99,]);

        $this->tenant = app(TenantRegistrationService::class)->register([
            'companyName' => 'Wayne Enterprises',
            'subdomain' => 'wayne-enterprises-' . Str::lower(Str::random(6)),
            'adminName' => 'Bruce Wayne',
            'adminEmail' => $email,
            'adminPassword' => $password,
            'planId' => $this->plan->id,
        ]);

        $this->user = GlobalIdentity::where('email', $email)->firstOrFail();
        $this->tenantDomain = $this->tenant->domains()->value('domain');

        // Entriamo nel DB del tenant per pescare l'utente locale (ci serve il suo role_id per il token)
        tenancy()->initialize($this->tenant);
        $this->localUser = User::where('global_user_id', $this->user->id)->firstOrFail();
        tenancy()->end();
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

    public function test_user_can_get_their_profile_with_valid_access_token()
    {
        // 1. Arrange: Generiamo un Access Token VERO
        $jwtService = app(JwtService::class);
        $accessToken = $jwtService->createTenantAccessToken(
            $this->user,
            $this->tenant->id,
            $this->localUser->role_id
        );

        // 2. Act: Chiamiamo l'endpoint /me passando il cookie
        $response = $this->call(
            'GET',
            'http://' . $this->tenantDomain . '/api/v1/auth/me',
            [],
            ['access_token' => $accessToken], // <-- Il cookie magico
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        // 3. Assert
        $response->assertStatus(200);

        // Verifichiamo che la struttura della risposta sia esattamente quella delle nostre API Resources
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'name', 'plan_id'],
                'role' => ['id', 'name', 'description'],
                'permissions' => [
                    '*' => ['id', 'slug'] // L'asterisco significa "un array di oggetti con questa struttura"
                ]
            ]
        ]);
    }

    public function test_me_fails_without_any_token()
    {
        // Act: Chiamiamo l'endpoint /me SENZA passare alcun cookie
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->getJson('http://' . $this->tenantDomain . '/api/v1/auth/me');

        // Assert: Ci aspettiamo il nostro "muro di gomma" standard
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Non autorizzato.']);
    }

    public function test_me_fails_if_refresh_token_is_used_instead_of_access()
    {
        // 1. Arrange: Generiamo un REFRESH token invece di un access token
        $jwtService = app(JwtService::class);
        $refreshToken = $jwtService->createRefreshToken($this->user);

        // 2. Act: Proviamo a ingannare il sistema passandolo come se fosse un access_token
        $response = $this->call(
            'GET',
            'http://' . $this->tenantDomain . '/api/v1/auth/me',
            [],
            ['access_token' => $refreshToken], // <-- Passiamo il token sbagliato
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        // 3. Assert: Il middleware deve accorgersi che il 'type' è sbagliato e bloccare tutto
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Non autorizzato.']);
    }

    public function test_me_fails_on_cross_tenant_token_forgery()
    {
        // 1. Arrange: Creiamo un SECONDO tenant al volo (es. Stark Industries)
        $otherTenant = app(TenantRegistrationService::class)->register([
            'companyName' => 'Stark Industries',
            'subdomain' => 'stark-industries-' . Str::lower(Str::random(6)),
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony@stark.com',
            'adminPassword' => 'IamIronman-123',
            'planId' => $this->plan->id,
        ]);

        // Generiamo un Access Token VERO, ma per il tenant di Tony Stark
        $jwtService = app(JwtService::class);
        $starkAccessToken = $jwtService->createTenantAccessToken(
            $this->user, // Bruce Wayne
            $otherTenant->id, // ma con l'ID della Stark Industries!
            1
        );

        // 2. Act: Bruce Wayne usa il token "Stark" per provare a entrare nel dominio "Wayne"
        $response = $this->call(
            'GET',
            'http://' . $this->tenantDomain . '/api/v1/auth/me',
            [],
            ['access_token' => $starkAccessToken], // <-- Token valido, ma per il posto sbagliato
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        // 3. Assert: Il middleware deve confrontare il tenant_id nel token con l'URL e bloccare tutto
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Non autorizzato.']);

        // 4. Pulizia manuale del secondo tenant dal DB condiviso
        tenancy()->initialize($otherTenant);
        User::where('tenant_id', $otherTenant->id)->forceDelete();
        Role::where('tenant_id', $otherTenant->id)->delete();
        Permission::where('tenant_id', $otherTenant->id)->delete();
        SlaPolicy::where('tenant_id', $otherTenant->id)->delete();
        Category::where('tenant_id', $otherTenant->id)->delete();
        tenancy()->end();
        $otherTenant->delete();
    }

    public function test_me_fails_if_local_profile_is_missing()
    {
        // 1. Arrange: Token validissimo
        $jwtService = app(JwtService::class);
        $accessToken = $jwtService->createTenantAccessToken(
            $this->user,
            $this->tenant->id,
            $this->localUser->role_id
        );

        // Simuliamo un errore di database o un utente cancellato malamente: rimuoviamo il profilo locale
        tenancy()->initialize($this->tenant);
        User::where('global_user_id', $this->user->id)->forceDelete();
        tenancy()->end();

        // 2. Act
        $response = $this->call(
            'GET',
            'http://' . $this->tenantDomain . '/api/v1/auth/me',
            [],
            ['access_token' => $accessToken],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        // 3. Assert: Il controller prova a cercare il localUser ma non lo trova
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Profilo locale non trovato in questo workspace']);
    }
}