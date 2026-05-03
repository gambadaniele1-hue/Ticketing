<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Resources\Global\GlobalIdentityResource;
use App\Http\Resources\Global\TenantResource;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\RefreshToken;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\JwtService;

class AuthController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        // 1. Il pacchetto Tenancy ha già letto il sottodominio e caricato il tenant!
        $currentTenant = tenant();

        // 2. Cerchiamo l'identità nel DB Globale (che è sempre accessibile)
        $user = GlobalIdentity::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Credenziali non valide', 'data' => null], 401);
        }

        // 3. Verifica Appartenenza Tenant (Usiamo l'ID preso in automatico!)
        $membership = $user->memberships()->where('tenant_id', $currentTenant->id)->first();
        if (!$membership || $membership->state !== 'accepted') {
            return response()->json(['message' => 'Non hai accesso a questa area di lavoro.', 'data' => null], 403);
        }

        // 4. Siamo GIÀ nel DB del tenant grazie al middleware, cerchiamo il profilo locale
        $localUser = User::where('global_user_id', $user->id)->first();

        if (!$localUser) {
            return response()->json(['message' => 'Profilo locale non trovato.', 'data' => null], 500);
        }

        // 5. Generazione Token (Inseriamo il tenant_id nel token per sicurezza interna)
        $accessToken = $this->jwtService->createTenantAccessToken($user, $currentTenant->id, $localUser->role_id);

        // Il service salva il refresh token in hash nel DB e restituisce il token raw per il cookie
        $refreshToken = $this->jwtService->createRefreshToken($user);

        // 6. Creazione Cookie
        $accessCookie = cookie('access_token', $accessToken, 60, '/', null, env('APP_ENV') !== 'local', true, false, 'Strict');
        $refreshCookie = cookie('refresh_token', $refreshToken, 10080, '/', null, env('APP_ENV') !== 'local', true, false, 'Strict');

        return response()->json([
            'message' => 'Login completato con successo',
            'data' => [
                'user' => new GlobalIdentityResource($user),
                'tenant' => new TenantResource($currentTenant),
            ]
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function refresh(Request $request)
    {
        // 1. Prendere il cookie dalla Request
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Non autorizzato: Nessun token di refresh fornito'], 401);
        }

        // 2. Il pacchetto Tenancy ha già caricato il tenant dal sottodominio!
        $currentTenant = tenant();

        // 3. Cerchiamo il token hashato nel DB Globale e verifichiamo che non sia scaduto.
        $tokenRecord = RefreshToken::where('token', hash('sha256', $refreshToken))
            ->where('revoked', 0)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token di refresh non valido o revocato'], 401);
        }

        // 4. Recuperiamo l'utente globale (usando l'ID salvato nel record del token)
        $user = GlobalIdentity::find($tokenRecord->global_identity_id);

        if (!$user) {
            return response()->json(['message' => 'Utente non trovato'], 404);
        }

        // 5. SICUREZZA: Controlliamo che l'utente sia ancora abilitato in QUESTO tenant
        $membership = $user->memberships()->where('tenant_id', $currentTenant->id)->first();
        if (!$membership || $membership->state !== 'accepted') {
            return response()->json(['message' => 'Non hai accesso a questa area di lavoro.'], 403);
        }

        // 6. Siamo già nel DB locale del tenant, quindi cerchiamo il profilo locale per prendere il ruolo
        $localUser = User::where('global_user_id', $user->id)->first();

        if (!$localUser) {
            return response()->json(['message' => 'Profilo locale non trovato.'], 500);
        }

        // 7. Generiamo il NUOVO Access Token!
        $newAccessToken = $this->jwtService->createTenantAccessToken($user, $currentTenant->id, $localUser->role_id);

        // 8. Prepariamo il nuovo Cookie protetto per l'Access Token (durata: 60 minuti)
        $accessCookie = cookie(
            'access_token',
            $newAccessToken,
            60,
            '/',
            null,
            env('APP_ENV') !== 'local', // Secure solo in produzione
            true,                       // HttpOnly
            false,
            'Strict'                    // SameSite
        );

        // 9. Rispondiamo con il nuovo cookie
        return response()->json([
            'message' => 'Sessione rinnovata con successo'
        ])->withCookie($accessCookie);
    }
}