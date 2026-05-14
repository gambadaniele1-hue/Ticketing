<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        // TODO: recuperare l'utente autenticato dalla request (già caricato da JwtMiddleware)

        // TODO: chiamare $user->hasPermission($slug) per verificare il permesso

        // TODO: se l'utente non ha il permesso → restituire response JSON 403
        //       con messaggio "Non autorizzato"

        // TODO: se l'utente ha il permesso → chiamare $next($request) e restituire la response

        return $next($request);
    }
}
