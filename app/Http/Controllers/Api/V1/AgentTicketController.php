<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTicketController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.view (già applicato dal middleware)

        // TODO: recuperare tutti i ticket del tenant corrente con stato 'open' o 'in_progress'
        //       includendo: titolo, stato, priorità, categoria, autore, resolver assegnato

        // TODO: restituire la lista ordinata per priorità e data di creazione
    }

    public function show(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.view (già applicato dal middleware)

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: recuperare i messaggi pubblici del ticket (is_internal = false)

        // TODO: recuperare le note interne del ticket (is_internal = true)

        // TODO: restituire il dettaglio completo con messaggi pubblici e note interne
    }

    public function take(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.assign (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che il ticket non sia già in carico a qualcuno → 422 se già assegnato
        //       oppure consentire la riassegnazione (verificare le regole di business)

        // TODO: impostare user_id_resolver = utente loggato e status = 'in_progress'

        // TODO: restituire il ticket aggiornato
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.reply (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che il ticket non sia chiuso → 422 se closed

        // TODO: validare il body:
        //       - body: richiesto, stringa
        //       - is_internal: opzionale, booleano (default false)

        // TODO: creare il messaggio associato al ticket con user_id = utente loggato
        //       e is_internal dal body (true = nota interna, false = messaggio pubblico)

        // TODO: restituire il messaggio creato con status 201
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.status (già applicato dal middleware)

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: validare il body:
        //       - status: richiesto, enum (open|in_progress|waiting|closed)

        // TODO: verificare che la transizione di stato sia valida
        //       (es. non si può riaprire un ticket closed senza logica specifica)

        // TODO: aggiornare lo status del ticket
        //       se status = 'closed' impostare anche closed_at = now()

        // TODO: restituire il ticket aggiornato
    }
}
