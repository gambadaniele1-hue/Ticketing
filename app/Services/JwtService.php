<?php

namespace App\Services;

use App\Models\Global\GlobalIdentity;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use stdClass;

class JwtService
{
    private string $secret;
    private string $algo = 'HS256';

    public function __construct()
    {
        // Recupera la chiave dal config che abbiamo impostato prima
        $this->secret = config('services.jwt.secret');
    }

    /**
     * Livello 1: Identity Token (Stateless, usato dopo l'OTP per vedere i tenant)
     */
    public function createIdentityToken(GlobalIdentity $user): string
    {
        $payload = [
            'iss' => config('app.url'),          // Issuer (Chi lo ha emesso)
            'iat' => time(),                     // Issued At (Quando)
            'exp' => time() + (60 * 15),         // Expiration (Scade tra 15 minuti)
            'type' => 'identity',                // Tipo di token
            'sub' => $user->id,                  // Subject (ID Globale Utente)
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Livello 2: Access Token (Stateless, usato per operare nel Tenant)
     */
    public function createTenantAccessToken(GlobalIdentity $user, string $tenantId, int $roleId): string
    {
        $payload = [
            'iss' => config('app.url'),
            'iat' => time(),
            'exp' => time() + (60 * 60 * 2),     // Scade tra 2 ore
            'type' => 'access',
            'sub' => $user->id,
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Verifica la firma e la validità temporale del Token.
     * Ritorna il payload decodificato, oppure lancia un'eccezione (ExpiredException, SignatureInvalidException)
     */
    public function verifyToken(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret, $this->algo));
    }

    /**
     * Livello 3: Refresh Token (Stateful)
     * Genera una stringa sicura a 64 caratteri e la salva nel database globale.
     */
    public function createRefreshToken(GlobalIdentity $user): string
    {
        // Genera una stringa crittograficamente sicura di 64 caratteri
        $token = Str::random(64);

        // Salva il token nel DB associato a questo utente
        $user->refreshTokens()->create([
            'token' => $token,
            'expires_at' => now()->addDays(30), // Il refresh token dura 30 giorni
            'revoked' => false,
        ]);

        return $token;
    }
}