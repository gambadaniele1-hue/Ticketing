<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\OtpCode;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\V1\VerifyOtpRequest;
use App\Http\Requests\V1\RequestOtpRequest;
use App\Jobs\SendOtpEmail;
use App\Models\Global\Tenant;
use App\Http\Resources\Global\TenantResource;
use App\Http\Requests\V1\SelectTenantRequest;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;


class OtpController extends Controller
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {

        // 1. Trova la global_identity tramite email
        $identity = GlobalIdentity::where('email', $request->email)->first();

        if (!$identity) {
            return response()->json(['message' => 'Codice non valido'], 422);
        }

        // 2. Trova l'OTP più recente non ancora usato
        $otp = OtpCode::where('global_identity_id', $identity->id)
            ->where('used', false)
            ->latest('expires_at')
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Codice non valido'], 422);
        }

        // 3. Verifica che non sia scaduto
        if ($otp->isExpired()) {
            return response()->json([
                'message' => 'Codice scaduto, richiedine uno nuovo',
            ], 422);
        }

        // 4. Verifica che il codice corrisponda
        if (!Hash::check($request->code, $otp->code)) {
            return response()->json(['message' => 'Codice non valido'], 422);
        }

        // 5. Marca OTP come usato
        $otp->update(['used' => true]);

        // 6. Marca l'identità come verificata (solo se non lo era già)
        if (!$identity->email_verified_at) {
            $identity->update(['email_verified_at' => now()]);
        }

        // 7. Rilascia identity token
        $identityToken = $this->jwtService->createIdentityToken($identity);

        return response()->json([
            'message' => 'Email verificata con successo',
            'identity_token' => $identityToken,
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $identity = GlobalIdentity::where('email', $request->email)->first();

        // Rispondiamo sempre 200 — no enumerazione utenti
        if (!$identity) {
            return response()->json([
                'message' => 'Se l\'email esiste riceverai un codice',
            ]);
        }

        // Invalida tutti gli OTP precedenti non ancora usati
        OtpCode::where('global_identity_id', $identity->id)
            ->where('used', false)
            ->update(['used' => true]);

        // Genera nuovo OTP
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'global_identity_id' => $identity->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Pubblica job su Redis
        SendOtpEmail::dispatch($identity->email, $code);

        return response()->json([
            'message' => 'Se l\'email esiste riceverai un codice',
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $globalUserId = $request->attributes->get('global_user_id');

        $tenants = Tenant::whereHas('memberships', function ($query) use ($globalUserId) {
            $query->where('global_user_id', $globalUserId)
                ->where('state', 'accepted');
        })->get();

        return response()->json([
            'data' => TenantResource::collection($tenants),
        ]);
    }

    public function selectTenant(SelectTenantRequest $request): JsonResponse
    {
        $globalUserId = $request->attributes->get('global_user_id');

        // 1. Verifica che il tenant esista
        $tenant = Tenant::where('id', $request->tenant_id)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant non trovato'], 404);
        }

        // 2. Verifica membership
        $membership = $tenant->memberships()
            ->where('global_user_id', $globalUserId)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'Non hai accesso a questo workspace',
                'error_code' => 'NO_MEMBERSHIP',
            ], 403);
        }

        if ($membership->state === 'pending') {
            return response()->json([
                'message' => 'Il tuo accesso è in attesa di approvazione',
                'error_code' => 'MEMBERSHIP_PENDING',
            ], 403);
        }

        if ($membership->state === 'banned') {
            return response()->json([
                'message' => 'Il tuo accesso è stato revocato',
                'error_code' => 'MEMBERSHIP_BANNED',
            ], 403);
        }

        // 3. Inizializza il tenant
        tenancy()->initialize($tenant);

        // 4. Trova il profilo locale
        $localUser = User::where('global_user_id', $globalUserId)->first();

        if (!$localUser) {
            tenancy()->end();
            return response()->json(['message' => 'Profilo utente non trovato'], 500);
        }

        // 5. Carica il ruolo
        $localUser->load('role');

        // 6. Genera i token
        $identity = GlobalIdentity::find($globalUserId);
        $accessToken = $this->jwtService->createTenantAccessToken($identity, $tenant->id, $localUser->role_id);
        $refreshToken = $this->jwtService->createRefreshToken($identity);

        // 7. Genera chiave handoff e salva in Redis
        $key = Str::random(32);

        Redis::setex("auth_handoff:{$key}", 30, json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]));

        // 8. Costruisci redirect URL usando il dominio reale del tenant
        $tenantDomain = $tenant->domains()->first()?->domain;

        if (!$tenantDomain) {
            tenancy()->end();
            return response()->json(['message' => 'Dominio tenant non trovato'], 500);
        }

        $scheme = request()->getScheme();
        $port = request()->getPort();
        $portPart = in_array($port, [80, 443], true) ? '' : ':' . $port;
        $redirectUrl = $scheme . '://' . $tenantDomain . $portPart . '/api/v1/auth/store-tokens?token=' . $key;

        tenancy()->end();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $identity->id,
                    'name' => $identity->name,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ],
                'role' => [
                    'name' => $localUser->role->name,
                ],
                'redirect_url' => $redirectUrl,
            ],
        ]);
    }
}