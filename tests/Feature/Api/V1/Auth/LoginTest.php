<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Tenant\User;
use App\Services\JwtService;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

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

    private function refreshUrlForDomain(string $domain): string
    {
        return 'http://' . $domain . '/api/v1/auth/refresh';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = 'I-am-batman-123';
        $this->email = 'bruce+' . Str::lower(Str::random(8)) . '@wayne.com';

        Event::fake([TenantCreated::class]);

        $this->plan = Plan::create([
            'name' => 'Test Plan',
            'description' => 'Piano di test',
            'price_month' => 9.99,
            'database_type' => 'shared',
        ]);

        $registrationData = [
            'companyName' => 'Wayne Enterprises',
            'subdomain' => 'wayne-enterprises',
            'adminName' => 'Bruce Wayne',
            'adminEmail' => $this->email,
            'adminPassword' => $this->password,
            'planId' => $this->plan->id,
        ];

        $this->tenant = app(TenantRegistrationService::class)->register($registrationData);
        $this->user = GlobalIdentity::where('email', $this->email)->firstOrFail();
        $this->tenantDomain = $this->tenant->domains()->value('domain');

        // 2. Creiamo il profilo locale dentro il Tenant
        tenancy()->initialize($this->tenant);

        try {
            User::create([
                'global_user_id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role_id' => 1, // Supponiamo che 1 sia Admin
            ]);
        } finally {
            tenancy()->end();
        }
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        // Controlliamo lo status
        $response->assertStatus(200);

        // Controlliamo la struttura JSON standard (message + data)
        $response->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'tenant' => ['id', 'name', 'plan_id']
            ]
        ]);

        // Controlliamo che i Cookie di sicurezza siano stati allegati!
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

    public function test_login_fails_if_user_does_not_belong_to_tenant()
    {
        // Creiamo un tenant con dominio diverso a cui Bruce Wayne NON appartiene
        $otherSubdomain = 'stark-industries-' . Str::lower(Str::random(8));

        $otherTenant = app(TenantRegistrationService::class)->register([
            'companyName' => 'Stark Industries',
            'subdomain' => $otherSubdomain,
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony+' . Str::lower(Str::random(8)) . '@stark.com',
            'adminPassword' => 'IamIronman-123',
            'planId' => $this->plan->id,
        ]);

        $otherTenantDomain = $otherTenant->domains()->value('domain');

        $response = $this->withServerVariables(['HTTP_HOST' => $otherTenantDomain])
            ->postJson($this->loginUrlForDomain($otherTenantDomain), [
                'email' => $this->email,
                'password' => $this->password, // Password giusta
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Non hai accesso a questa area di lavoro.']);
    }

    public function test_login_requires_validation()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->loginUrlForDomain($this->tenantDomain), []);

        // Il FormRequest deve bloccare tutto
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_refresh_fails_without_refresh_cookie()
    {
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->refreshUrlForDomain($this->tenantDomain), []);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Non autorizzato: Nessun token di refresh fornito']);
    }

    public function test_refresh_fails_with_invalid_refresh_cookie()
    {
        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => 'refresh-token-non-valido'],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Token di refresh non valido o revocato']);
    }

    public function test_refresh_returns_new_access_cookie_with_valid_refresh_token()
    {
        $refreshToken = app(JwtService::class)->createRefreshToken($this->user);

        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => $refreshToken],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Sessione rinnovata con successo']);
        $response->assertCookie('access_token');
    }
}