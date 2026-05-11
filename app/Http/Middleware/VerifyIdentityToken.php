<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIdentityToken
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token mancante'], 401);
        }

        try {
            $payload = $this->jwtService->verifyToken($token);

            // Verifica che sia un identity token e non un access token
            if ($payload->type !== 'identity') {
                return response()->json(['message' => 'Token non valido'], 401);
            }

            // Salva il payload nella request per usarlo nel controller
            // Salva il payload nella request per usarlo nel controller
            $request->attributes->add([
                'identity_payload' => $payload,
                'global_user_id' => $payload->sub,
            ]);

        } catch (ExpiredException $e) {
            return response()->json(['message' => 'Token scaduto'], 401);
        } catch (SignatureInvalidException $e) {
            return response()->json(['message' => 'Token non valido'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token non valido'], 401);
        }

        return $next($request);
    }
}