# TODO — Rotte da implementare

## Ticketing API v1

Tutte le rotte sono sotto `/api/v1/` e vanno aggiunte in `routes/api_v1.php`.

## ⚙️ PRIMA DI TUTTO — Middleware Permessi

### `CheckPermission` Middleware

**File:** `app/Http/Middleware/CheckPermission.php`
**Alias:** `permission` (da registrare in `bootstrap/app.php`)

Verifica che l'utente autenticato abbia il permesso richiesto tramite il metodo `hasPermission(slug)` già implementato sul modello `User`.

```php
// Utilizzo nelle route:
Route::middleware(['jwt.auth', 'permission:admin.dashboard'])->group(function () { ... });
Route::middleware(['jwt.auth', 'permission:tickets.view'])->group(function () { ... });
```

Logica:

1. Legge l'utente dal JWT (già caricato da `JwtMiddleware`)
2. Chiama `$user->hasPermission($slug)`
3. Se non ha il permesso → `403 Non autorizzato`
4. Se ha il permesso → lascia passare

**Slug dei permessi da usare per ogni area:**

| Area                       | Slug                |
| -------------------------- | ------------------- |
| Dashboard Admin            | `admin.dashboard`   |
| Gestione utenti            | `users.manage`      |
| Gestione team              | `team.manage`       |
| Gestione categorie         | `categories.manage` |
| Gestione SLA               | `sla.manage`        |
| Visualizzazione macro      | `macros.view`       |
| Visualizzazione ticket     | `tickets.view`      |
| Creazione ticket           | `tickets.create`    |
| Risposta ticket            | `tickets.reply`     |
| Chiusura ticket            | `tickets.close`     |
| Presa in carico ticket     | `tickets.assign`    |
| Aggiornamento stato ticket | `tickets.status`    |

> Implementa e testa questo middleware prima di procedere con qualsiasi rotta.
> Verifica anche che il seeder dei ruoli assegni correttamente questi slug ai ruoli Admin, Agent, Team Lead e Customer.

---

## 🔐 Auth — Registrazione Utente (Dominio Tenant)

### POST `/api/v1/auth/register`

**Controller:** `AuthController@register`
**Auth:** Pubblica
**Già implementata** — verifica che sia presente e funzionante.

Richiede:

```json
{ "name": "string", "email": "email", "password": "min:8" }
```

Risponde:

```json
{ "message": "Registrazione completata. Sei in attesa di approvazione." }
```

Dopo la registrazione → flusso OTP verifica email → redirect a login.

---

## 📊 Admin — Stats (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/stats`

**Controller:** `AdminStatsController@index`
Conta i ticket per stato nel tenant corrente.

```json
{ "data": { "open": 12, "in_progress": 5, "waiting": 3, "closed": 48 } }
```

---

## 👥 Admin — Utenti (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/users`

**Controller:** `AdminUserController@index`
Lista utenti del tenant con ruolo, stato membership e team.
Query param opzionale: `?state=pending|accepted|suspended`

### PATCH `/api/v1/admin/users/{id}/approve`

**Controller:** `AdminUserController@approve`
Imposta membership `state = accepted`.

### PATCH `/api/v1/admin/users/{id}/reject`

**Controller:** `AdminUserController@reject`
Imposta membership `state = rejected`.

### PATCH `/api/v1/admin/users/{id}/suspend`

**Controller:** `AdminUserController@suspend`
Imposta membership `state = suspended`.

### PATCH `/api/v1/admin/users/{id}/reactivate`

**Controller:** `AdminUserController@reactivate`
Imposta membership `state = accepted` da `suspended`.

### PATCH `/api/v1/admin/users/{id}/role`

**Controller:** `AdminUserController@updateRole`
Aggiorna il `role_id` dell'utente nel DB tenant.

```json
{ "role_id": 2 }
```

---

## 🏢 Admin — Team (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/teams`

**Controller:** `AdminTeamController@index`
Lista team con nome e conteggio membri.

### POST `/api/v1/admin/teams`

**Controller:** `AdminTeamController@store`
Crea un nuovo team.

```json
{ "name": "Supporto Tecnico" }
```

### PUT `/api/v1/admin/teams/{id}`

**Controller:** `AdminTeamController@update`
Aggiorna il nome del team.

```json
{ "name": "Nuovo nome" }
```

### DELETE `/api/v1/admin/teams/{id}`

**Controller:** `AdminTeamController@destroy`
Elimina il team.

### GET `/api/v1/admin/teams/{id}/members`

**Controller:** `AdminTeamController@members`
Lista membri del team con nome, email e ruolo nel team.

### POST `/api/v1/admin/teams/{id}/members`

**Controller:** `AdminTeamController@addMember`
Aggiunge un utente al team con il ruolo specificato.

```json
{ "user_id": 2, "team_role": "Agent" }
```

### DELETE `/api/v1/admin/teams/{id}/members/{user_id}`

**Controller:** `AdminTeamController@removeMember`
Rimuove un utente dal team.

### PATCH `/api/v1/admin/teams/{id}/members/{user_id}/role`

**Controller:** `AdminTeamController@updateMemberRole`
Aggiorna il ruolo di un membro nel team.

```json
{ "team_role": "Team Lead" }
```

---

## 🏷️ Admin — Categorie (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/categories`

**Controller:** `AdminCategoryController@index`
Lista categorie con gerarchia parent/children e team associati.

### POST `/api/v1/admin/categories`

**Controller:** `AdminCategoryController@store`
Crea una categoria. `parent_id` opzionale per sottocategorie.

```json
{ "name": "Hardware", "parent_id": 1 }
```

### PUT `/api/v1/admin/categories/{id}`

**Controller:** `AdminCategoryController@update`
Aggiorna nome e/o categoria padre.

### DELETE `/api/v1/admin/categories/{id}`

**Controller:** `AdminCategoryController@destroy`
Elimina la categoria.

### POST `/api/v1/admin/categories/{id}/teams`

**Controller:** `AdminCategoryController@attachTeam`
Associa un team alla categoria (tabella pivot `category_team`).

```json
{ "team_id": 1 }
```

### DELETE `/api/v1/admin/categories/{id}/teams/{team_id}`

**Controller:** `AdminCategoryController@detachTeam`
Rimuove l'associazione tra categoria e team.

---

## ⏱️ Admin — SLA (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/sla`

**Controller:** `AdminSlaController@index`
Lista SLA policy con nome, priorità, ore risposta e risoluzione.

### POST `/api/v1/admin/sla`

**Controller:** `AdminSlaController@store`
Crea una nuova SLA policy.

```json
{
    "name": "Urgente",
    "priority": "high",
    "response_time_hours": 2,
    "resolution_time_hours": 8
}
```

### PUT `/api/v1/admin/sla/{id}`

**Controller:** `AdminSlaController@update`
Aggiorna una SLA esistente.

### DELETE `/api/v1/admin/sla/{id}`

**Controller:** `AdminSlaController@destroy`
Elimina una SLA policy.

---

## 📝 Admin — Macro (Dominio Tenant, JWT + ruolo Admin)

### GET `/api/v1/admin/macros`

**Controller:** `AdminMacroController@index`
Lista macro globali e di team con titolo, contenuto e team associato.

---

## 👤 Customer — Ticket (Dominio Tenant, JWT + ruolo Customer)

### GET `/api/v1/customer/tickets`

**Controller:** `CustomerTicketController@index`
Lista ticket aperti dall'utente loggato.

### POST `/api/v1/customer/tickets`

**Controller:** `CustomerTicketController@store`
Crea un nuovo ticket.

```json
{ "title": "...", "description": "...", "priority": "medium", "category_id": 1 }
```

### GET `/api/v1/customer/tickets/{id}`

**Controller:** `CustomerTicketController@show`
Dettaglio di un ticket (solo se autore è l'utente loggato).

### POST `/api/v1/customer/tickets/{id}/messages`

**Controller:** `CustomerTicketController@sendMessage`
Invia un messaggio pubblico nel ticket.

```json
{ "body": "..." }
```

### PATCH `/api/v1/customer/tickets/{id}/close`

**Controller:** `CustomerTicketController@close`
Chiude il ticket (solo se autore è l'utente loggato).

---

## 🔧 Agent — Ticket (Dominio Tenant, JWT + ruolo Agent o Team Lead)

### GET `/api/v1/agent/tickets`

**Controller:** `AgentTicketController@index`
Lista ticket del tenant (aperti e in lavorazione).

### GET `/api/v1/agent/tickets/{id}`

**Controller:** `AgentTicketController@show`
Dettaglio di un ticket con messaggi pubblici e note interne.

### PATCH `/api/v1/agent/tickets/{id}/take`

**Controller:** `AgentTicketController@take`
Prende in carico il ticket — imposta `user_id_resolver` all'agente loggato e `status = in_progress`.

### POST `/api/v1/agent/tickets/{id}/messages`

**Controller:** `AgentTicketController@sendMessage`
Invia un messaggio nel ticket. Se `is_internal: true` è una nota interna.

```json
{ "body": "...", "is_internal": false }
```

### PATCH `/api/v1/agent/tickets/{id}/status`

**Controller:** `AgentTicketController@updateStatus`
Aggiorna lo stato del ticket.

```json
{ "status": "waiting" }
```
