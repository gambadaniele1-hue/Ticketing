<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: recuperare tutte le categorie del tenant corrente
        //       includendo la gerarchia parent/children (relazioni ricorsive o eager load)
        //       e i team associati tramite la pivot category_team

        // TODO: restituire la lista con struttura gerarchica
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: validare il body:
        //       - name: richiesto, stringa, max 255
        //       - parent_id: opzionale, deve esistere nella tabella categories

        // TODO: creare la categoria nel DB del tenant corrente

        // TODO: restituire la categoria creata con status 201
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: trovare la categoria $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body:
        //       - name: opzionale, stringa, max 255
        //       - parent_id: opzionale, deve esistere nella tabella categories
        //         verificare che non crei un ciclo (una categoria non può essere figlia di sé stessa)

        // TODO: aggiornare i campi forniti

        // TODO: restituire la categoria aggiornata
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: trovare la categoria $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che la categoria non abbia ticket attivi associati → 422 se ha dipendenze

        // TODO: verificare che non abbia sottocategorie → 422 o gestire la cancellazione a cascata

        // TODO: eliminare la categoria e le sue associazioni con i team (detach dalla pivot)

        // TODO: restituire response 204 No Content
    }

    public function attachTeam(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: trovare la categoria $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body: team_id richiesto, deve esistere nella tabella teams

        // TODO: verificare che il team non sia già associato alla categoria → 422 se già presente

        // TODO: inserire il record nella tabella pivot category_team

        // TODO: restituire response di successo con status 201
    }

    public function detachTeam(int $id, int $teamId): JsonResponse
    {
        // TODO: verificare permesso permission:categories.manage (già applicato dal middleware)

        // TODO: trovare la categoria $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che il team $teamId sia associato alla categoria → 404 se non trovato

        // TODO: rimuovere il record dalla tabella pivot category_team

        // TODO: restituire response 204 No Content
    }
}
