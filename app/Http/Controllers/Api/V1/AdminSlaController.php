<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSlaController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:sla.manage (già applicato dal middleware)

        // TODO: recuperare tutte le SLA policy del tenant corrente
        //       campi: name, priority, response_time_hours, resolution_time_hours

        // TODO: restituire la lista ordinata per priority
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: verificare permesso permission:sla.manage (già applicato dal middleware)

        // TODO: validare il body:
        //       - name: richiesto, stringa, max 255
        //       - priority: richiesto, enum (low|medium|high|urgent)
        //       - response_time_hours: richiesto, intero positivo
        //       - resolution_time_hours: richiesto, intero positivo, >= response_time_hours

        // TODO: creare la SLA policy nel DB del tenant corrente

        // TODO: restituire la policy creata con status 201
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:sla.manage (già applicato dal middleware)

        // TODO: trovare la SLA policy $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body (tutti opzionali):
        //       - name: stringa, max 255
        //       - priority: enum (low|medium|high|urgent)
        //       - response_time_hours: intero positivo
        //       - resolution_time_hours: intero positivo

        // TODO: aggiornare i campi forniti

        // TODO: restituire la policy aggiornata
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:sla.manage (già applicato dal middleware)

        // TODO: trovare la SLA policy $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che la policy non sia usata da ticket attivi → 422 se ha dipendenze

        // TODO: eliminare la SLA policy

        // TODO: restituire response 204 No Content
    }
}
