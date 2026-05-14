<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:admin.dashboard (già applicato dal middleware)

        // TODO: recuperare il tenant corrente (già inizializzato da InitializeTenancyByDomain)

        // TODO: contare i ticket per stato nel DB del tenant corrente:
        //       - open
        //       - in_progress
        //       - waiting
        //       - closed

        // TODO: restituire i conteggi con AdminStatsResource (o JSON diretto)
        //       formato: { "data": { "open": X, "in_progress": X, "waiting": X, "closed": X } }
    }
}
