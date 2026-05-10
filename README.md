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
DB_DATABASE=ticketing_global
DB_USERNAME=root
DB_PASSWORD=

# 6. Esegui le migration
php artisan migrate

# 7. Avvia il server
php artisan serve
```

---

## 🔐 Autenticazione

Il sistema usa **JWT custom** (`firebase/php-jwt`) con tre livelli di token, trasportati via cookie **HttpOnly + Secure + SameSite=Strict**.

| Token          | Durata    | Scopo                                                                |
| -------------- | --------- | -------------------------------------------------------------------- |
| Identity Token | 15 minuti | Flusso OTP — identifica l'utente prima della scelta del tenant       |
| Access Token   | 1 ora     | Autorizza le operazioni nel tenant, contiene `tenant_id` e `role_id` |
| Refresh Token  | 7 giorni  | Rinnova l'access token, salvato nel DB come hash SHA-256             |

Il middleware `JwtMiddleware` verifica il `tenant_id` su ogni richiesta protetta per prevenire accessi cross-tenant.

---

## 🌐 Endpoint API

Tutti gli endpoint sono sotto `/api/v1/`. Il routing tenant è gestito dal middleware `InitializeTenancyByDomain`.

### Autenticazione

| Metodo | Path                      | Descrizione                                                                                    | Auth              |
| ------ | ------------------------- | ---------------------------------------------------------------------------------------------- | ----------------- |
| `POST` | `/api/v1/register-tenant` | Registrazione nuova azienda — verifica sottodominio, crea tenant, DB e utente Admin, invia OTP | Pubblica          |
| `POST` | `/api/v1/auth/login`      | Login con email e password                                                                     | Pubblica (tenant) |
| `POST` | `/api/v1/auth/refresh`    | Rinnovo access token                                                                           | Pubblica (tenant) |
| `GET`  | `/api/v1/auth/me`         | Dati utente corrente                                                                           | JWT               |

> Gli endpoint per ticket, messaggi, team, categorie e SLA sono in sviluppo.

---

## 🏗️ Struttura del progetto

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   └── TenantRegistrationController.php
│   ├── Middleware/
│   │   ├── JwtMiddleware.php
│   │   └── ForceJsonResponse.php
│   ├── Requests/V1/
│   │   ├── LoginRequest.php
│   │   └── StoreTenantRequest.php
│   └── Resources/V1/
│       ├── GlobalIdentityResource.php
│       ├── TenantResource.php
│       ├── RoleResource.php
│       └── PermissionResource.php
├── Models/
│   ├── Global/
│   │   ├── GlobalIdentity.php
│   │   ├── Plan.php
│   │   ├── Tenant.php
│   │   ├── TenantMembership.php
│   │   └── RefreshToken.php
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
│   └── TenantRegistrationService.php
├── Jobs/
│   ├── CreateTenantAdminUser.php
│   └── CreateTenantMysqlUser.php
├── Traits/
│   └── BelongsToTenantHybrid.php
└── Exceptions/
    └── DatabaseAlreadyExistsException.php
```

---

## 🗄️ Database

Il sistema usa due livelli di database:

**DB Globale** — identità utenti, tenant, piani, membership, refresh token.

**DB Tenant** — dati operativi isolati per ogni azienda: utenti locali, ruoli, ticket, messaggi, team, categorie, SLA.

Per approfondire lo schema completo consulta la [documentazione del progetto](https://github.com/gambadaniele1-hue/ticketing-docs/blob/main/01-progetto.md).

---

## 📦 Repository collegate

| Repository                                                              | Descrizione                                  |
| ----------------------------------------------------------------------- | -------------------------------------------- |
| [`ticketing-mail`](https://github.com/gambadaniele1-hue/ticketing-mail) | Microservizio Go per l'invio email via Redis |
| [`ticketing-app`](https://github.com/gambadaniele1-hue/ticketing-app)   | Frontend Lovable                             |
| [`ticketing-docs`](https://github.com/gambadaniele1-hue/ticketing-docs) | Documentazione completa                      |

---

## 👤 Autore

Progetto realizzato come elaborato di quinta superiore — Informatica.

---

_API v1.1 — Laravel 12_
