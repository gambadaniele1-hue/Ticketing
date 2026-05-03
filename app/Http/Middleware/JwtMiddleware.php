<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\JwtService;
use App\Models\Global\GlobalIdentity;
use Exception;
use Firebase\JWT\ExpiredException;

class JwtMiddleware
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cerchiamo il token nel Cookie (o come fallback nell'Header Authorization per i test con Postman)
        $token = $request->cookie('access_token') ?? $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Non autorizzato: Token mancante'], 401);
        }

        try {
            // 2. Usiamo il nostro fidato service per validare la firma e la scadenza
            $payload = $this->jwtService->verifyToken($token);

            // 3. Controlliamo che tipo di token ci hanno passato
            if ($payload->type !== 'access') {
                return response()->json(['message' => 'Non autorizzato: Tipo di token non valido'], 403);
            }

            // 4. Recuperiamo l'identità globale per comodità
            // Usiamo Auth::loginUsingId() o semplicemente lo inietto nella request
            $user = GlobalIdentity::find($payload->sub);

            if (!$user) {
                return response()->json(['message' => 'Utente non trovato'], 404);
            }

            // 5. Inietto i dati puliti e pronti nella Request
            // Così in qualsiasi controller potrai fare: $request->attributes->get('tenant_id')
            $request->attributes->add([
                'global_user' => $user,
                'tenant_id' => $payload->tenant_id ?? null,
                'role_id' => $payload->role_id ?? null,
            ]);

            // Se tutto va bene, facciamo passare la richiesta al controller
            return $next($request);

        } catch (ExpiredException $e) {
            return response()->json([
                'message' => 'Token scaduto',
                'code' => 'TOKEN_EXPIRED' // Questo codice serve al frontend per capire che deve usare il refresh token
            ], 401);
        } catch (Exception $e) {
            return response()->json(['message' => 'Token non valido: ' . $e->getMessage()], 401);
        }
    }
}