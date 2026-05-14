<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminMacroController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:macros.view (già applicato dal middleware)

        // TODO: recuperare tutte le macro del tenant corrente:
        //       - macro globali (non associate a nessun team)
        //       - macro di team (associate a un team specifico)
        //       campi: title, content, team associato (nome o null)

        // TODO: restituire la lista distinta tra globali e di team
    }
}
