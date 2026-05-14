<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: leggere query param opzionale ?state=pending|accepted|suspended

        // TODO: recuperare tutti gli utenti del tenant corrente tramite la tabella memberships
        //       includendo: nome, email, ruolo, stato membership e team associati

        // TODO: filtrare per state se il parametro è presente

        // TODO: restituire la lista con una UserResource (o collection JSON)
    }

    public function approve(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: trovare la membership dell'utente $id nel tenant corrente
        //       → 404 se non trovato

        // TODO: impostare membership state = 'accepted'

        // TODO: salvare e restituire response di successo
    }

    public function reject(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: trovare la membership dell'utente $id nel tenant corrente
        //       → 404 se non trovato

        // TODO: impostare membership state = 'rejected'

        // TODO: salvare e restituire response di successo
    }

    public function suspend(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: trovare la membership dell'utente $id nel tenant corrente
        //       → 404 se non trovato

        // TODO: impostare membership state = 'suspended'

        // TODO: salvare e restituire response di successo
    }

    public function reactivate(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: trovare la membership dell'utente $id nel tenant corrente
        //       → 404 se non trovato

        // TODO: verificare che lo stato attuale sia 'suspended'
        //       → errore 422 se non è suspended

        // TODO: impostare membership state = 'accepted'

        // TODO: salvare e restituire response di successo
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:users.manage (già applicato dal middleware)

        // TODO: validare il body: role_id richiesto, deve esistere nella tabella roles

        // TODO: trovare l'utente $id nel tenant corrente → 404 se non trovato

        // TODO: aggiornare il role_id dell'utente nel DB del tenant

        // TODO: restituire response di successo con il ruolo aggiornato
    }
}
