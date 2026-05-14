<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TenantRegistrationController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AdminTeamController;
use App\Http\Controllers\Api\V1\AdminCategoryController;
use App\Http\Controllers\Api\V1\AdminSlaController;
use App\Http\Controllers\Api\V1\AdminMacroController;
use App\Http\Controllers\Api\V1\CustomerTicketController;
use App\Http\Controllers\Api\V1\AgentTicketController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// =============================================================================
// DOMINIO CENTRALE — routes accessibili da localhost:8000
// Queste route NON richiedono un sottodominio tenant.
// =============================================================================

// -----------------------------------------------------------------------------
// Registrazione tenant — pubblica
// -----------------------------------------------------------------------------
Route::post('/register-tenant', [TenantRegistrationController::class, 'store']);

// -----------------------------------------------------------------------------
// Piani disponibili — pubblica
// Usata dal frontend nella pagina di registrazione per mostrare i piani
// -----------------------------------------------------------------------------
Route::get('/plans', [PlanController::class, 'index']);

// -----------------------------------------------------------------------------
// Global Login — flusso OTP multi-tenant
// Step 1: richiedi OTP via email
// Step 2: verifica OTP → ricevi identity token
// -----------------------------------------------------------------------------
Route::post('/auth/global-login/request-otp', [OtpController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [OtpController::class, 'verify']);

// -----------------------------------------------------------------------------
// Global Login — selezione tenant (richiede identity token)
// Step 3: lista tenant dell'utente
// Step 4: seleziona tenant → ricevi redirect_url per il cookie handoff
// -----------------------------------------------------------------------------
Route::middleware('identity.auth')->group(function () {
    Route::get('/auth/global-login/tenants', [OtpController::class, 'tenants']);
    Route::post('/auth/global-login/select-tenant', [OtpController::class, 'selectTenant']);
});

// =============================================================================
// DOMINIO TENANT — routes accessibili da {tenant}.localhost:8000
// Tutte le route qui dentro richiedono un sottodominio tenant valido.
// InitializeTenancyByDomain si connette al DB del tenant automaticamente.
// PreventAccessFromCentralDomains blocca l'accesso dal dominio centrale.
// =============================================================================
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // -------------------------------------------------------------------------
    // Info tenant — pubblica
    // Chiamata dal frontend nella pagina di login per mostrare nome e descrizione
    // -------------------------------------------------------------------------
    Route::get('/tenant/info', [TenantController::class, 'info']);

    // -------------------------------------------------------------------------
    // Auth tenant — route pubbliche (utente non ancora loggato)
    // -------------------------------------------------------------------------
    Route::prefix('/auth')->group(function () {

        // Cookie handoff — riceve il token monouso da Redis e setta i cookie HttpOnly
        Route::get('/store-tokens', [AuthController::class, 'storeTokens']);

        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        // TODO: POST /auth/register — AuthController@register
        //       Registrazione utente nel dominio tenant
        //       Flusso: crea utente → flusso OTP verifica email → attende approvazione admin
        Route::post('/register', [AuthController::class, 'register']);

        // -------------------------------------------------------------------------
        // Auth tenant — route protette (richiede access token JWT)
        // -------------------------------------------------------------------------
        Route::middleware('jwt.auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // =========================================================================
    // AREA ADMIN — JWT + permesso specifico per ogni gruppo
    // =========================================================================
    Route::middleware('jwt.auth')->prefix('/admin')->group(function () {

        // -------------------------------------------------------------------------
        // Stats
        // -------------------------------------------------------------------------

        // TODO: GET /admin/stats — AdminStatsController@index
        //       Conta i ticket per stato nel tenant corrente
        //       Risposta: { data: { open, in_progress, waiting, closed } }
        Route::middleware('permission:admin.dashboard')
            ->get('/stats', [AdminStatsController::class, 'index']);

        // -------------------------------------------------------------------------
        // Gestione utenti
        // -------------------------------------------------------------------------
        Route::middleware('permission:users.manage')->group(function () {

            // TODO: GET /admin/users — AdminUserController@index
            //       Lista utenti del tenant con ruolo, stato membership e team
            //       Query param opzionale: ?state=pending|accepted|suspended
            Route::get('/users', [AdminUserController::class, 'index']);

            // TODO: PATCH /admin/users/{id}/approve — AdminUserController@approve
            //       Imposta membership state = 'accepted'
            Route::patch('/users/{id}/approve', [AdminUserController::class, 'approve']);

            // TODO: PATCH /admin/users/{id}/reject — AdminUserController@reject
            //       Imposta membership state = 'rejected'
            Route::patch('/users/{id}/reject', [AdminUserController::class, 'reject']);

            // TODO: PATCH /admin/users/{id}/suspend — AdminUserController@suspend
            //       Imposta membership state = 'suspended'
            Route::patch('/users/{id}/suspend', [AdminUserController::class, 'suspend']);

            // TODO: PATCH /admin/users/{id}/reactivate — AdminUserController@reactivate
            //       Imposta membership state = 'accepted' (da suspended)
            Route::patch('/users/{id}/reactivate', [AdminUserController::class, 'reactivate']);

            // TODO: PATCH /admin/users/{id}/role — AdminUserController@updateRole
            //       Aggiorna il role_id dell'utente nel DB tenant
            //       Body: { "role_id": 2 }
            Route::patch('/users/{id}/role', [AdminUserController::class, 'updateRole']);
        });

        // -------------------------------------------------------------------------
        // Gestione team
        // -------------------------------------------------------------------------
        Route::middleware('permission:team.manage')->group(function () {

            // TODO: GET /admin/teams — AdminTeamController@index
            //       Lista team con nome e conteggio membri
            Route::get('/teams', [AdminTeamController::class, 'index']);

            // TODO: POST /admin/teams — AdminTeamController@store
            //       Crea un nuovo team — Body: { "name": "Supporto Tecnico" }
            Route::post('/teams', [AdminTeamController::class, 'store']);

            // TODO: PUT /admin/teams/{id} — AdminTeamController@update
            //       Aggiorna il nome del team — Body: { "name": "Nuovo nome" }
            Route::put('/teams/{id}', [AdminTeamController::class, 'update']);

            // TODO: DELETE /admin/teams/{id} — AdminTeamController@destroy
            //       Elimina il team
            Route::delete('/teams/{id}', [AdminTeamController::class, 'destroy']);

            // TODO: GET /admin/teams/{id}/members — AdminTeamController@members
            //       Lista membri del team con nome, email e ruolo nel team
            Route::get('/teams/{id}/members', [AdminTeamController::class, 'members']);

            // TODO: POST /admin/teams/{id}/members — AdminTeamController@addMember
            //       Aggiunge un utente al team — Body: { "user_id": 2, "team_role": "Agent" }
            Route::post('/teams/{id}/members', [AdminTeamController::class, 'addMember']);

            // TODO: DELETE /admin/teams/{id}/members/{user_id} — AdminTeamController@removeMember
            //       Rimuove un utente dal team
            Route::delete('/teams/{id}/members/{user_id}', [AdminTeamController::class, 'removeMember']);

            // TODO: PATCH /admin/teams/{id}/members/{user_id}/role — AdminTeamController@updateMemberRole
            //       Aggiorna il ruolo di un membro nel team — Body: { "team_role": "Team Lead" }
            Route::patch('/teams/{id}/members/{user_id}/role', [AdminTeamController::class, 'updateMemberRole']);
        });

        // -------------------------------------------------------------------------
        // Gestione categorie
        // -------------------------------------------------------------------------
        Route::middleware('permission:categories.manage')->group(function () {

            // TODO: GET /admin/categories — AdminCategoryController@index
            //       Lista categorie con gerarchia parent/children e team associati
            Route::get('/categories', [AdminCategoryController::class, 'index']);

            // TODO: POST /admin/categories — AdminCategoryController@store
            //       Crea una categoria — Body: { "name": "Hardware", "parent_id": 1 }
            Route::post('/categories', [AdminCategoryController::class, 'store']);

            // TODO: PUT /admin/categories/{id} — AdminCategoryController@update
            //       Aggiorna nome e/o categoria padre
            Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);

            // TODO: DELETE /admin/categories/{id} — AdminCategoryController@destroy
            //       Elimina la categoria
            Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

            // TODO: POST /admin/categories/{id}/teams — AdminCategoryController@attachTeam
            //       Associa un team alla categoria — Body: { "team_id": 1 }
            Route::post('/categories/{id}/teams', [AdminCategoryController::class, 'attachTeam']);

            // TODO: DELETE /admin/categories/{id}/teams/{team_id} — AdminCategoryController@detachTeam
            //       Rimuove l'associazione tra categoria e team
            Route::delete('/categories/{id}/teams/{team_id}', [AdminCategoryController::class, 'detachTeam']);
        });

        // -------------------------------------------------------------------------
        // Gestione SLA
        // -------------------------------------------------------------------------
        Route::middleware('permission:sla.manage')->group(function () {

            // TODO: GET /admin/sla — AdminSlaController@index
            //       Lista SLA policy con nome, priorità, ore risposta e risoluzione
            Route::get('/sla', [AdminSlaController::class, 'index']);

            // TODO: POST /admin/sla — AdminSlaController@store
            //       Crea una nuova SLA policy
            //       Body: { "name": "Urgente", "priority": "high", "response_time_hours": 2, "resolution_time_hours": 8 }
            Route::post('/sla', [AdminSlaController::class, 'store']);

            // TODO: PUT /admin/sla/{id} — AdminSlaController@update
            //       Aggiorna una SLA esistente
            Route::put('/sla/{id}', [AdminSlaController::class, 'update']);

            // TODO: DELETE /admin/sla/{id} — AdminSlaController@destroy
            //       Elimina una SLA policy
            Route::delete('/sla/{id}', [AdminSlaController::class, 'destroy']);
        });

        // -------------------------------------------------------------------------
        // Macro
        // -------------------------------------------------------------------------

        // TODO: GET /admin/macros — AdminMacroController@index
        //       Lista macro globali e di team con titolo, contenuto e team associato
        Route::middleware('permission:macros.view')
            ->get('/macros', [AdminMacroController::class, 'index']);
    });

    // =========================================================================
    // AREA CUSTOMER — JWT + permesso specifico per ogni rotta
    // =========================================================================
    Route::middleware('jwt.auth')->prefix('/customer')->group(function () {

        // TODO: GET /customer/tickets — CustomerTicketController@index
        //       Lista ticket aperti dall'utente loggato
        Route::middleware('permission:tickets.view')
            ->get('/tickets', [CustomerTicketController::class, 'index']);

        // TODO: POST /customer/tickets — CustomerTicketController@store
        //       Crea un nuovo ticket
        //       Body: { "title": "...", "description": "...", "priority": "medium", "category_id": 1 }
        Route::middleware('permission:tickets.create')
            ->post('/tickets', [CustomerTicketController::class, 'store']);

        // TODO: GET /customer/tickets/{id} — CustomerTicketController@show
        //       Dettaglio di un ticket (solo se autore è l'utente loggato)
        Route::middleware('permission:tickets.view')
            ->get('/tickets/{id}', [CustomerTicketController::class, 'show']);

        // TODO: POST /customer/tickets/{id}/messages — CustomerTicketController@sendMessage
        //       Invia un messaggio pubblico nel ticket — Body: { "body": "..." }
        Route::middleware('permission:tickets.reply')
            ->post('/tickets/{id}/messages', [CustomerTicketController::class, 'sendMessage']);

        // TODO: PATCH /customer/tickets/{id}/close — CustomerTicketController@close
        //       Chiude il ticket (solo se autore è l'utente loggato)
        Route::middleware('permission:tickets.close')
            ->patch('/tickets/{id}/close', [CustomerTicketController::class, 'close']);
    });

    // =========================================================================
    // AREA AGENT — JWT + permesso specifico per ogni rotta
    // =========================================================================
    Route::middleware('jwt.auth')->prefix('/agent')->group(function () {

        // TODO: GET /agent/tickets — AgentTicketController@index
        //       Lista ticket del tenant (aperti e in lavorazione)
        Route::middleware('permission:tickets.view')
            ->get('/tickets', [AgentTicketController::class, 'index']);

        // TODO: GET /agent/tickets/{id} — AgentTicketController@show
        //       Dettaglio di un ticket con messaggi pubblici e note interne
        Route::middleware('permission:tickets.view')
            ->get('/tickets/{id}', [AgentTicketController::class, 'show']);

        // TODO: PATCH /agent/tickets/{id}/take — AgentTicketController@take
        //       Prende in carico il ticket: user_id_resolver = agente, status = 'in_progress'
        Route::middleware('permission:tickets.assign')
            ->patch('/tickets/{id}/take', [AgentTicketController::class, 'take']);

        // TODO: POST /agent/tickets/{id}/messages — AgentTicketController@sendMessage
        //       Invia un messaggio nel ticket — Body: { "body": "...", "is_internal": false }
        Route::middleware('permission:tickets.reply')
            ->post('/tickets/{id}/messages', [AgentTicketController::class, 'sendMessage']);

        // TODO: PATCH /agent/tickets/{id}/status — AgentTicketController@updateStatus
        //       Aggiorna lo stato del ticket — Body: { "status": "waiting" }
        Route::middleware('permission:tickets.status')
            ->patch('/tickets/{id}/status', [AgentTicketController::class, 'updateStatus']);
    });
});
