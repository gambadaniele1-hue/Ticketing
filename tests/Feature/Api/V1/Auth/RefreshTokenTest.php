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

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;
    protected GlobalIdentity $user;
    protected Tenant $tenant;
    protected string $email;
    protected string $password;
    protected string $tenantDomain;

    private function refreshUrlForDomain(string $domain): string
    {
        return 'http://' . $domain . '/api/v1/auth/refresh';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = 'I-am-batman-123';
        $this->email = 'bruce+' . Str::lower(Str::random(8)) . '@wayne.com';

        // 1. Creiamo il piano
        $this->plan = Plan::create([
            'name' => 'Test Plan Shared',
            'price_month' => 9.99,
            'database_type' => 'shared',
        ]);

        // 2. Dati per il Service
        $registrationData = [
            'companyName' => 'Wayne Enterprises',
            'subdomain' => 'wayne-enterprises-' . Str::lower(Str::random(6)),
            'adminName' => 'Bruce Wayne',
            'adminEmail' => $this->email,
            'adminPassword' => $this->password,
            'planId' => $this->plan->id,
        ];

        // 3. Registriamo tramite Service (crea tenant, user, membership, local profile, ecc.)
        $this->tenant = app(TenantRegistrationService::class)->register($registrationData);
        $this->user = GlobalIdentity::where('email', $this->email)->firstOrFail();
        $this->tenantDomain = $this->tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        // Usciamo da eventuali tenancy appese
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if (isset($this->tenant)) {
            $isShared = $this->tenant->plan->database_type === 'shared';

            if ($isShared) {
                // Pulizia profonda del DB condiviso
                tenancy()->initialize($this->tenant);
                User::where('tenant_id', $this->tenant->id)->forceDelete();
                Role::where('tenant_id', $this->tenant->id)->delete();
                Permission::where('tenant_id', $this->tenant->id)->delete();
                SlaPolicy::where('tenant_id', $this->tenant->id)->delete();
                Category::where('tenant_id', $this->tenant->id)->delete();
                tenancy()->end();
            }

            // Eliminazione Tenant e dominio
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    // ==========================================
    // I TEST DEL REFRESH TOKEN
    // ==========================================

    public function test_user_can_refresh_token_with_valid_cookie()
    {
        // 1. Arrange
        $jwtService = app(JwtService::class);
        $refreshTokenString = $jwtService->createRefreshToken($this->user);

        // 2. Act
        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => $refreshTokenString],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        // 3. Assert - Risposta base
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Sessione rinnovata con successo']);

        // 4. Assert - CONTROLLI SUPER-BLINDATI SUL COOKIE
        // Verifica che esista
        $response->assertCookie('access_token');

        // Verifica che non sia già scaduto appena emesso
        $response->assertCookieNotExpired('access_token');
    }

    public function test_refresh_fails_without_refresh_cookie()
    {
        // Act: Nessun cookie inviato
        $response = $this->withServerVariables(['HTTP_HOST' => $this->tenantDomain])
            ->postJson($this->refreshUrlForDomain($this->tenantDomain), []);

        // Assert
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Non autorizzato: Nessun token di refresh fornito']);
    }

    public function test_refresh_fails_with_invalid_refresh_cookie()
    {
        // Act: Inviamo un cookie spazzatura
        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => 'questo-token-non-esiste'],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        // Assert
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Token di refresh non valido o revocato']);
    }

    public function test_refresh_fails_if_membership_is_not_accepted()
    {
        // Arrange: 
        // 1. Creiamo un token valido per l'utente
        $jwtService = app(JwtService::class);
        $refreshTokenString = $jwtService->createRefreshToken($this->user);

        // 2. Sospendiamo la membership!
        $this->user->memberships()->where('tenant_id', $this->tenant->id)->update(['state' => 'pending']);

        // Act: Proviamo a fare refresh con il token valido
        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => $refreshTokenString],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        // Assert: Il sistema deve capire che l'utente non è più abilitato per questo tenant
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Non hai accesso a questa area di lavoro.']);
    }

    public function test_refresh_fails_if_local_profile_is_missing()
    {
        // Arrange: 
        // 1. Creiamo un token valido
        $jwtService = app(JwtService::class);
        $refreshTokenString = $jwtService->createRefreshToken($this->user);

        // 2. Simuliamo un disallineamento: cancelliamo l'utente dal database del tenant!
        tenancy()->initialize($this->tenant);
        User::where('global_user_id', $this->user->id)->forceDelete();
        tenancy()->end();

        // Act: Proviamo a fare refresh
        $response = $this->call(
            'POST',
            $this->refreshUrlForDomain($this->tenantDomain),
            [],
            ['refresh_token' => $refreshTokenString],
            [],
            [
                'HTTP_HOST' => $this->tenantDomain,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        // Assert: Il controller prova a cercare il profilo locale ma non lo trova
        $response->assertStatus(500);
        $response->assertJson(['message' => 'Profilo locale non trovato.']);
    }
}