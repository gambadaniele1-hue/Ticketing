# 🎫 Ticketing API

### Backend REST — Laravel 12

> API backend del sistema di ticketing multi-tenant ibrido. Gestisce autenticazione, routing per tenant, ruoli, permessi e l'intero ciclo di vita dei ticket.

---

## 📌 Panoramica

**Ticketing API** è il cuore del sistema. Espone endpoint REST versionati sotto `/api/v1/` e gestisce l'isolamento multi-tenant tramite sottodominio, indirizzando ogni richiesta al database corretto in modo automatico e trasparente.

---

## 🛠️ Stack

| Componente       | Versione |
| ---------------- | -------- |
| PHP              | 8.2+     |
| Laravel          | 12.0     |
| MySQL            | —        |
| Redis            | 7.x      |
| stancl/tenancy   | ^3.10    |
| firebase/php-jwt | ^7.0     |

---

## ⚙️ Installazione

```bash
# 1. Clona la repository
git clone https://github.com/gambadaniele1-hue/ticketing-api.git
cd ticketing-api

# 2. Installa le dipendenze
composer install

# 3. Copia il file di configurazione
cp .env.example .env

# 4. Genera la chiave applicativa
php artisan key:generate

# 5. Configura il database nel file .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ticketing
DB_USERNAME=root
DB_PASSWORD=

# 6. Esegui le migration
php artisan migrate:fresh --seed

# 7. Migra il database tenant condiviso
php artisan migrate:fresh --database="shared" --path="database/migrations/tenant"

# 8. Avvia il server
php artisan serve

# 9. Avvia il worker Redis (terminale separato)
php artisan queue:work
```

---

## 🔐 Autenticazione

Il sistema usa **JWT custom** (`firebase/php-jwt`) con tre livelli di token, trasportati via cookie **HttpOnly + Secure + SameSite=Strict**.

| Token          | Durata    | Scopo                                                                |
| -------------- | --------- | -------------------------------------------------------------------- |
| Identity Token | 15 minuti | Flusso OTP — identifica l'utente prima della scelta del tenant       |
| Access Token   | 1 ora     | Autorizza le operazioni nel tenant, contiene `tenant_id` e `role_id` |
| Refresh Token  | 7 giorni  | Rinnova l'access token, salvato nel DB come hash SHA-256             |

Il middleware `JwtMiddleware` verifica il `tenant_id` su ogni richiesta protetta per prevenire accessi cross-tenant. Il middleware `VerifyIdentityToken` protegge gli endpoint del flusso OTP globale.

---

## 🌐 Endpoint API

### Dominio Centrale (`localhost`)

| Metodo | Path                                      | Descrizione                                | Auth           |
| ------ | ----------------------------------------- | ------------------------------------------ | -------------- |
| `POST` | `/api/v1/register-tenant`                 | Registrazione nuova azienda                | Pubblica       |
| `GET`  | `/api/v1/plans`                           | Lista piani disponibili                    | Pubblica       |
| `POST` | `/api/v1/auth/global-login/request-otp`   | Richiesta OTP per login senza sottodominio | Pubblica       |
| `POST` | `/api/v1/auth/global-login/verify-otp`    | Verifica OTP, emette Identity Token        | Pubblica       |
| `GET`  | `/api/v1/auth/global-login/tenants`       | Lista tenant dell'utente                   | Identity Token |
| `POST` | `/api/v1/auth/global-login/select-tenant` | Selezione tenant, handoff Redis            | Identity Token |
| `POST` | `/api/v1/auth/otp/verify`                 | Verifica OTP registrazione tenant          | Pubblica       |

### Dominio Tenant (`{subdomain}.localhost`)

| Metodo | Path                        | Descrizione                     | Auth     |
| ------ | --------------------------- | ------------------------------- | -------- |
| `GET`  | `/api/v1/tenant/info`       | Info tenant corrente            | Pubblica |
| `POST` | `/api/v1/auth/login`        | Login con email e password      | Pubblica |
| `POST` | `/api/v1/auth/register`     | Registrazione utente nel tenant | Pubblica |
| `POST` | `/api/v1/auth/refresh`      | Rinnovo access token            | Pubblica |
| `GET`  | `/api/v1/auth/me`           | Dati utente corrente            | JWT      |
| `POST` | `/api/v1/auth/logout`       | Logout, revoca refresh token    | JWT      |
| `GET`  | `/api/v1/auth/store-tokens` | Handoff token cross-domain      | Pubblica |

### Endpoint Admin (`{subdomain}.localhost`) — Stub pronti

| Metodo                | Path                                              | Descrizione                  | Auth                      |
| --------------------- | ------------------------------------------------- | ---------------------------- | ------------------------- |
| `GET`                 | `/api/v1/admin/stats`                             | Statistiche ticket per stato | JWT + `admin.dashboard`   |
| `GET`                 | `/api/v1/admin/users`                             | Lista utenti                 | JWT + `users.manage`      |
| `PATCH`               | `/api/v1/admin/users/{id}/approve`                | Approva membership           | JWT + `users.manage`      |
| `PATCH`               | `/api/v1/admin/users/{id}/reject`                 | Rifiuta membership           | JWT + `users.manage`      |
| `PATCH`               | `/api/v1/admin/users/{id}/suspend`                | Sospende utente              | JWT + `users.manage`      |
| `PATCH`               | `/api/v1/admin/users/{id}/reactivate`             | Riattiva utente              | JWT + `users.manage`      |
| `PATCH`               | `/api/v1/admin/users/{id}/role`                   | Cambia ruolo                 | JWT + `users.manage`      |
| `GET/POST/PUT/DELETE` | `/api/v1/admin/teams`                             | CRUD team                    | JWT + `team.manage`       |
| `GET/POST/DELETE`     | `/api/v1/admin/teams/{id}/members`                | Gestione membri              | JWT + `team.manage`       |
| `PATCH`               | `/api/v1/admin/teams/{id}/members/{user_id}/role` | Cambia ruolo membro          | JWT + `team.manage`       |
| `GET/POST/PUT/DELETE` | `/api/v1/admin/categories`                        | CRUD categorie               | JWT + `categories.manage` |
| `POST/DELETE`         | `/api/v1/admin/categories/{id}/teams`             | Associa/rimuovi team         | JWT + `categories.manage` |
| `GET/POST/PUT/DELETE` | `/api/v1/admin/sla`                               | CRUD SLA policy              | JWT + `sla.manage`        |
| `GET`                 | `/api/v1/admin/macros`                            | Lista macro                  | JWT + `macros.view`       |

### Endpoint Customer (`{subdomain}.localhost`) — Stub pronti

| Metodo  | Path                                     | Descrizione         | Auth                   |
| ------- | ---------------------------------------- | ------------------- | ---------------------- |
| `GET`   | `/api/v1/customer/tickets`               | Lista ticket utente | JWT + `tickets.view`   |
| `POST`  | `/api/v1/customer/tickets`               | Crea ticket         | JWT + `tickets.create` |
| `GET`   | `/api/v1/customer/tickets/{id}`          | Dettaglio ticket    | JWT + `tickets.view`   |
| `POST`  | `/api/v1/customer/tickets/{id}/messages` | Invia messaggio     | JWT + `tickets.reply`  |
| `PATCH` | `/api/v1/customer/tickets/{id}/close`    | Chiude ticket       | JWT + `tickets.close`  |

### Endpoint Agent (`{subdomain}.localhost`) — Stub pronti

| Metodo  | Path                                  | Descrizione              | Auth                   |
| ------- | ------------------------------------- | ------------------------ | ---------------------- |
| `GET`   | `/api/v1/agent/tickets`               | Lista ticket tenant      | JWT + `tickets.view`   |
| `GET`   | `/api/v1/agent/tickets/{id}`          | Dettaglio ticket         | JWT + `tickets.view`   |
| `PATCH` | `/api/v1/agent/tickets/{id}/take`     | Prende in carico         | JWT + `tickets.assign` |
| `POST`  | `/api/v1/agent/tickets/{id}/messages` | Messaggio o nota interna | JWT + `tickets.reply`  |
| `PATCH` | `/api/v1/agent/tickets/{id}/status`   | Aggiorna stato           | JWT + `tickets.status` |

> La logica di tutti gli endpoint stub è pianificata per le fasi successive.

---

## 🏗️ Struttura del progetto

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   ├── OtpController.php
│   │   ├── TenantRegistrationController.php
│   │   ├── TenantController.php
│   │   └── PlanController.php
│   ├── Middleware/
│   │   ├── JwtMiddleware.php
│   │   ├── VerifyIdentityToken.php
│   │   └── ForceJsonResponse.php
│   ├── Requests/V1/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterUserRequest.php
│   │   ├── StoreTenantRequest.php
│   │   ├── RequestOtpRequest.php
│   │   ├── VerifyOtpRequest.php
│   │   └── SelectTenantRequest.php
│   └── Resources/V1/
│       ├── GlobalIdentityResource.php
│       ├── TenantResource.php
│       ├── TenantInfoResource.php
│       ├── PlanResource.php
│       ├── RoleResource.php
│       └── PermissionResource.php
├── Models/
│   ├── Global/
│   │   ├── GlobalIdentity.php
│   │   ├── Plan.php
│   │   ├── Tenant.php
│   │   ├── TenantMembership.php
│   │   ├── RefreshToken.php
│   │   └── OtpCode.php
│   └── Tenant/
│       ├── User.php
│       ├── Role.php
│       ├── Permission.php
│       ├── Team.php
│       ├── Category.php
│       ├── Ticket.php
│       ├── Message.php
│       └── SlaPolicy.php
├── Services/
│   ├── JwtService.php
│   ├── TenantRegistrationService.php
│   └── UserRegistrationService.php
├── Jobs/
│   ├── CreateTenantAdminUser.php
│   ├── CreateTenantMysqlUser.php
│   ├── SendOtpEmail.php
│   ├── SendWelcomeEmail.php
│   └── NotifyAdminNewUser.php
├── Traits/
│   └── BelongsToTenantHybrid.php
└── Exceptions/
    └── DatabaseAlreadyExistsException.php

resources/
└── views/
    └── emails/
        ├── otp.blade.php
        ├── welcome.blade.php
        └── notify-admin-new-user.blade.php
```

---

## 🗄️ Database

Il sistema usa due livelli di database:

**DB Globale** — identità utenti, tenant, piani, membership, refresh token, OTP codes.

**DB Tenant** — dati operativi isolati per ogni azienda: utenti locali, ruoli, permessi, ticket, messaggi, team, categorie, SLA.

Per approfondire lo schema completo consulta la [documentazione del progetto](https://github.com/gambadaniele1-hue/ticketing-docs/blob/main/01-progetto.md).

---

## 🧪 Testing

```bash
php artisan test
```

La suite copre: registrazione tenant, autenticazione JWT, flusso OTP, refresh token, endpoint `/me` e sicurezza cross-tenant.

---

## 📦 Repository collegate

| Repository                                                              | Descrizione                                  |
| ----------------------------------------------------------------------- | -------------------------------------------- |
| [`ticketing-mail`](https://github.com/gambadaniele1-hue/ticketing-mail) | Microservizio Go per l'invio email via Redis |
| [`ticketing-app`](https://github.com/gambadaniele1-hue/ticketing-app)   | Frontend React + Tailwind                    |
| [`ticketing-docs`](https://github.com/gambadaniele1-hue/ticketing-docs) | Documentazione completa                      |

---

## 👤 Autore

Progetto realizzato come elaborato di quinta superiore — Informatica.

---

_API v1.3 — Laravel 12_
