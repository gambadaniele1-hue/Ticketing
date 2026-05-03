<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DatabaseAlreadyExistsException extends Exception
{
    /**
     * Trasforma l'eccezione in una risposta HTTP per le chiamate API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request): JsonResponse
    {
        // Rispondiamo sempre con un JSON strutturato e uno status 500 (Internal Server Error)
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(), // Prende il messaggio che hai passato nel Service
        ], 500);
    }
}