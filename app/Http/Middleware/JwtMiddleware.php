<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\JwtService;
use App\Models\Global\GlobalIdentity;
use Exception;
use Illuminate\Support\Facades\Log; // <--- Importata la facciata Log

class JwtMiddleware
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token') ?? $request->bearerToken();

        // La risposta "muro di gomma" standard per il frontend
        $unauthorizedResponse = response()->json(['message' => 'Non autorizzato.'], 401);

        if (!$token) {
            Log::debug('JwtMiddleware: Accesso negato. Nessun token fornito.');
            return $unauthorizedResponse;
        }

        try {
            // Validazione firma e scadenza
            $payload = $this->jwtService->verifyToken($token);

            // Controllo sul tipo di token
            if ($payload->type !== 'access') {
                Log::warning("JwtMiddleware: Tentativo di utilizzo di un token errato (Tipo ricevuto: {$payload->type}).");
                return $unauthorizedResponse;
            }

            // CONTROLLO DI SICUREZZA CROSS-TENANT
            if (isset($payload->tenant_id) && $payload->tenant_id !== tenant('id')) {
                Log::warning("JwtMiddleware: Violazione Cross-Tenant! Tentativo di usare un token del tenant [{$payload->tenant_id}] nel tenant [" . tenant('id') . "].");
                return $unauthorizedResponse;
            }

            $user = GlobalIdentity::find($payload->sub);

            if (!$user) {
                Log::warning("JwtMiddleware: Utente con ID {$payload->sub} non trovato nel database globale.");
                return $unauthorizedResponse;
            }

            // Tutto pulito. Passiamo i dati alla Request
            $request->attributes->add([
                'global_user' => $user,
                'tenant_id' => $payload->tenant_id ?? null,
                'role_id' => $payload->role_id ?? null,
            ]);

            return $next($request);

        } catch (Exception $e) {
            // Cattura token scaduti, malformati o firme non valide
            Log::debug("JwtMiddleware: Token rifiutato. Motivo: " . $e->getMessage());
            return $unauthorizedResponse;
        }
    }
}