# 🎫 Ticketing API
### Backend REST — Laravel 12

> API backend del sistema di ticketing multi-tenant ibrido. Gestisce autenticazione, routing per tenant, ruoli, permessi e l'intero ciclo di vita dei ticket.

---

## 📌 Panoramica

**Ticketing API** è il cuore del sistema. Espone endpoint REST versionati sotto `/api/v1/` e gestisce l'isolamento multi-tenant tramite sottodominio, indirizzando ogni richiesta al database corretto in modo automatico e trasparente.

---

## 🛠️ Stack

| Componente | Versione |
|---|---|
| PHP | 8.2+ |
| Laravel | 12.0 |
| MySQL | — |
| Redis | 7.x |
| stancl/tenancy | ^3.10 |
| firebase/php-jwt | ^7.0 |

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

| Token | Durata | Scopo |
|---|---|---|
| Identity Token | 15 minuti | Flusso OTP — identifica l'utente prima della scelta del tenant |
| Access Token | 1 ora | Autorizza le operazioni nel tenant, contiene `tenant_id` e `role_id` |
| Refresh Token | 7 giorni | Rinnova l'access token, salvato nel DB come hash SHA-256 |

Il middleware `JwtMiddleware` verifica il `tenant_id` su ogni richiesta protetta per prevenire accessi cross-tenant. Il middleware `VerifyIdentityToken` protegge gli endpoint del flusso OTP globale.

---

## 🌐 Endpoint API

### Dominio Centrale (`localhost`)

| Metodo | Path | Descrizione | Auth |
|---|---|---|---|
| `POST` | `/api/v1/register-tenant` | Registrazione nuova azienda | Pubblica |
| `GET` | `/api/v1/plans` | Lista piani disponibili | Pubblica |
| `POST` | `/api/v1/auth/global-login/request-otp` | Richiesta OTP per login senza sottodominio | Pubblica |
| `POST` | `/api/v1/auth/global-login/verify-otp` | Verifica OTP, emette Identity Token | Pubblica |
| `GET` | `/api/v1/auth/global-login/tenants` | Lista tenant dell'utente | Identity Token |
| `POST` | `/api/v1/auth/global-login/select-tenant` | Selezione tenant, handoff Redis | Identity Token |
| `POST` | `/api/v1/auth/otp/verify` | Verifica OTP registrazione tenant | Pubblica |

### Dominio Tenant (`{subdomain}.localhost`)

| Metodo | Path | Descrizione | Auth |
|---|---|---|---|
| `GET` | `/api/v1/tenant/info` | Info tenant corrente | Pubblica |
| `POST` | `/api/v1/auth/login` | Login con email e password | Pubblica |
| `POST` | `/api/v1/auth/register` | Registrazione utente nel tenant | Pubblica |
| `POST` | `/api/v1/auth/refresh` | Rinnovo access token | Pubblica |
| `GET` | `/api/v1/auth/me` | Dati utente corrente | JWT |
| `POST` | `/api/v1/auth/logout` | Logout, revoca refresh token | JWT |
| `GET` | `/api/v1/auth/store-tokens` | Handoff token cross-domain | Pubblica |

> Gli endpoint Admin (stats, users, teams, categorie, SLA, macro) sono in sviluppo.

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

| Repository | Descrizione |
|---|---|
| [`ticketing-mail`](https://github.com/gambadaniele1-hue/ticketing-mail) | Microservizio Go per l'invio email via Redis |
| [`ticketing-app`](https://github.com/gambadaniele1-hue/ticketing-app) | Frontend React + Tailwind |
| [`ticketing-docs`](https://github.com/gambadaniele1-hue/ticketing-docs) | Documentazione completa |

---

## 👤 Autore

Progetto realizzato come elaborato di quinta superiore — Informatica.

---

*API v1.2 — Laravel 12*
