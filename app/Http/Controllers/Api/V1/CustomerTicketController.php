<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTicketController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.view (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: recuperare tutti i ticket aperti dall'utente loggato nel tenant corrente
        //       (filtrare per user_id_creator = utente loggato)

        // TODO: restituire la lista con i campi essenziali (title, status, priority, created_at)
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.create (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: validare il body:
        //       - title: richiesto, stringa, max 255
        //       - description: richiesto, stringa
        //       - priority: richiesto, enum (low|medium|high|urgent)
        //       - category_id: richiesto, deve esistere nella tabella categories del tenant

        // TODO: creare il ticket nel DB del tenant con:
        //       - user_id_creator = utente loggato
        //       - status = 'open'
        //       - applicare la SLA policy in base alla priority e categoria

        // TODO: restituire il ticket creato con status 201
    }

    public function show(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.view (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che user_id_creator del ticket corrisponda all'utente loggato
        //       → 403 se l'utente non è l'autore del ticket

        // TODO: restituire il dettaglio completo del ticket con i messaggi pubblici
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.reply (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che user_id_creator del ticket corrisponda all'utente loggato
        //       → 403 se l'utente non è l'autore del ticket

        // TODO: verificare che il ticket non sia chiuso → 422 se closed

        // TODO: validare il body: body richiesto, stringa

        // TODO: creare il messaggio pubblico (is_internal = false) associato al ticket
        //       con user_id = utente loggato

        // TODO: restituire il messaggio creato con status 201
    }

    public function close(int $id): JsonResponse
    {
        // TODO: verificare permesso permission:tickets.close (già applicato dal middleware)

        // TODO: recuperare l'utente autenticato dalla request

        // TODO: trovare il ticket $id nel tenant corrente → 404 se non trovato

        // TODO: verificare che user_id_creator del ticket corrisponda all'utente loggato
        //       → 403 se l'utente non è l'autore del ticket

        // TODO: verificare che il ticket non sia già chiuso → 422 se già closed

        // TODO: impostare status = 'closed' e closed_at = now()

        // TODO: restituire il ticket aggiornato
    }
}
