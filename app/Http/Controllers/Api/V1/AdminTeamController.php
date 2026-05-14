<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTeamController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: recuperare tutti i team del tenant corrente
        //       includendo il conteggio dei membri (count della relazione users/members)

        // TODO: restituire la lista con nome e conteggio membri
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: validare il body: name richiesto, stringa, max 255

        // TODO: creare il nuovo team nel DB del tenant corrente

        // TODO: restituire il team creato con status 201
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body: name richiesto, stringa, max 255

        // TODO: aggiornare il nome del team

        // TODO: restituire il team aggiornato
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che il team non abbia ticket aperti o in lavorazione assegnati
        //       → errore 422 se ha dipendenze attive

        // TODO: eliminare il team (e le sue relazioni tramite cascade o manualmente)

        // TODO: restituire response 204 No Content
    }

    public function members(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: recuperare tutti i membri del team con:
        //       nome, email e ruolo nel team (team_role dalla pivot)

        // TODO: restituire la lista dei membri
    }

    public function addMember(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body: user_id richiesto, team_role richiesto (Agent|Team Lead)

        // TODO: verificare che user_id esista nel tenant corrente → 404 se non trovato

        // TODO: verificare che l'utente non sia già membro del team → 422 se già presente

        // TODO: aggiungere l'utente al team con il team_role specificato nella tabella pivot

        // TODO: restituire response di successo con status 201
    }

    public function removeMember(int $id, int $userId): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che l'utente $userId sia membro del team → 404 se non trovato

        // TODO: rimuovere l'utente dal team (detach dalla pivot)

        // TODO: restituire response 204 No Content
    }

    public function updateMemberRole(Request $request, int $id, int $userId): JsonResponse
    {
        // TODO: verificare permesso permission:team.manage (già applicato dal middleware)

        // TODO: trovare il team $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che l'utente $userId sia membro del team → 404 se non trovato

        // TODO: validare il body: team_role richiesto (Agent|Team Lead)

        // TODO: aggiornare il team_role nella tabella pivot

        // TODO: restituire response di successo con il ruolo aggiornato
    }
}
