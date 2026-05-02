<?php

namespace Tests\Feature\Services;

use App\Models\Global\GlobalIdentity;
use App\Services\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase;

    protected JwtService $jwtService;
    protected GlobalIdentity $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Inizializziamo il service
        $this->jwtService = new JwtService();

        // Creiamo un utente fittizio nel database centrale per i test
        $this->user = GlobalIdentity::create([
            'name' => 'Tony Stark',
            'email' => 'tony@stark.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_it_creates_valid_identity_token()
    {
        $token = $this->jwtService->createIdentityToken($this->user);

        // Verifica che sia una stringa JWT (3 parti separate da punto)
        $this->assertIsString($token);
        $this->assertEquals(2, substr_count($token, '.'));

        // Decodifichiamo per controllare il payload
        $payload = $this->jwtService->verifyToken($token);

        $this->assertEquals('identity', $payload->type);
        $this->assertEquals($this->user->id, $payload->sub);
        $this->assertEquals($this->user->email, $payload->email);
    }

    public function test_it_creates_valid_tenant_access_token()
    {
        $tenantId = 'stark-industries';
        $roleId = 1; // Es. Admin

        $token = $this->jwtService->createTenantAccessToken($this->user, $tenantId, $roleId);

        $payload = $this->jwtService->verifyToken($token);

        $this->assertEquals('access', $payload->type);
        $this->assertEquals($this->user->id, $payload->sub);
        $this->assertEquals($tenantId, $payload->tenant_id);
        $this->assertEquals($roleId, $payload->role_id);
    }

    public function test_it_throws_exception_on_invalid_signature()
    {
        // Creiamo un token valido
        $token = $this->jwtService->createIdentityToken($this->user);

        // Manomettiamo l'ultimo carattere della firma
        $manipulatedToken = substr($token, 0, -1) . 'a';

        // Diciamo a PHPUnit che ci aspettiamo un'eccezione di firma non valida
        $this->expectException(SignatureInvalidException::class);

        $this->jwtService->verifyToken($manipulatedToken);
    }

    public function test_it_throws_exception_on_expired_token()
    {
        // Per testare la scadenza, generiamo un token manualmente usando la chiave del config,
        // ma impostando l'Expiration Time (exp) nel passato (es. 10 secondi fa)
        $secret = config('services.jwt.secret');
        $payload = [
            'iat' => time() - 3600,
            'exp' => time() - 10, // Scaduto!
            'sub' => $this->user->id,
        ];

        $expiredToken = JWT::encode($payload, $secret, 'HS256');

        // Diciamo a PHPUnit che ci aspettiamo un'eccezione di token scaduto
        $this->expectException(ExpiredException::class);

        $this->jwtService->verifyToken($expiredToken);
    }

    public function test_it_creates_and_saves_refresh_token_to_database()
    {
        $token = $this->jwtService->createRefreshToken($this->user);

        // 1. Controlla che ritorni una stringa
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));

        // 2. Controlla che sia stata salvata correttamente nel DB centrale
        $this->assertDatabaseHas('refresh_tokens', [
            'global_identity_id' => $this->user->id,
            'token' => $token,
            'revoked' => 0,
        ]);
    }
}