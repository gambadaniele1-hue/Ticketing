# Branch Summary: feature/otp vs main

Questo documento riassume lo stato attuale del branch `feature/otp` rispetto a `main`, includendo sia le modifiche già committate sul branch sia il worktree attuale non ancora committato. Se ti serve solo la parte committata, puoi considerare la sezione "Worktree corrente" come appendice operativa.

## Visione d'insieme

Il branch introduce tre blocchi funzionali principali:

1. Registrazione tenant più robusta, con provisioning di identità globale, tenant, dominio, membership e avvio dei job infrastrutturali.
2. Flusso auth completo con OTP, identity token, login tenant-scoped, refresh token e handoff via Redis per il passaggio tra sottodominio e cookie HttpOnly.
3. Copertura test più ampia per registration, login, refresh, me endpoint e service di provisioning tenant, con casi sia positivi sia di rollback/failure.

## File modificati o creati

### `.env.example`

- **Cosa fa**: espone i valori di configurazione di esempio per l'ambiente locale, inclusi i parametri cookie di sessione.
- **Perché è stato aggiunto/modificato**: per allineare il template con il codice auth che legge `config('session.*')`, evitando valori hardcoded nel controller.
- **Come si collega al resto del sistema**: alimenta `config/session.php` e rende prevedibile il comportamento dei cookie in login, refresh e handoff.

### `.env.testing`

- **Cosa fa**: definisce l'ambiente di test per eseguire la suite su MySQL e Redis con valori di base controllati.
- **Perché è stato aggiunto/modificato**: serve a far girare correttamente i test feature e service del branch, soprattutto quelli che toccano tenancy, Redis e queue.
- **Come si collega al resto del sistema**: viene letto da `phpunit.xml` durante l'esecuzione dei test.

### `app/Http/Controllers/Api/V1/AuthController.php`

- **Cosa fa**: gestisce login tenant-scoped, refresh token, endpoint `me` e, nel worktree attuale, anche lo storage dei token di handoff via Redis.
- **Perché è stato aggiunto/modificato**: il login ora rilascia cookie HttpOnly configurati via `session.php`; il refresh rigenera solo l'access token; `storeTokens` completa il passaggio OTP/identity-login verso il tenant domain.
- **Come si collega al resto del sistema**: usa `JwtService` per creare token, `RefreshToken` per la persistenza del refresh hash, `GlobalIdentity` e `User` per verificare membership e profilo locale, e i Resource per la risposta API.

### `app/Jobs/NotifyAdminNewUser.php`

- **Cosa fa**: prepara una notifica email per gli admin quando un nuovo utente richiede accesso al workspace.
- **Perché è stato aggiunto/modificato**: il branch aggiunge il flusso di approvazione e serve un avviso automatico agli admin.
- **Come si collega al resto del sistema**: viene dispatchato da `UserRegistrationService` dopo la creazione della membership pending; usa la view `emails.notify-admin-new-user` e una coda Redis-based via `mail:queue`.

### `app/Jobs/SendOtpEmail.php`

- **Cosa fa**: pubblica in Redis il messaggio email contenente il codice OTP.
- **Perché è stato aggiunto/modificato**: introduce il canale per consegnare l'OTP generato al momento della registrazione tenant.
- **Come si collega al resto del sistema**: è dispatchato da `TenantRegistrationService` e usa la view `emails.otp` per il rendering HTML.

### `app/Jobs/SendWelcomeEmail.php`

- **Cosa fa**: genera la mail di benvenuto per l'utente appena abilitato al workspace.
- **Perché è stato aggiunto/modificato**: completa il set di notifiche del flusso onboarding.
- **Come si collega al resto del sistema**: usa la view `emails.welcome` e invia il payload nella coda `mail:queue` via Redis.

### `app/Models/Global/OtpCode.php`

- **Cosa fa**: modella gli OTP salvati nel database globale con expiry e flag `used`.
- **Perché è stato aggiunto/modificato**: serve a verificare, invalidare e marcare gli OTP nel flusso di verifica email.
- **Come si collega al resto del sistema**: viene usato da `OtpController` e `TenantRegistrationService` per generare, controllare e invalidare i codici.

### `app/Models/Tenant/Role.php`

- **Cosa fa**: rappresenta i ruoli del tenant e le relazioni con permessi e utenti.
- **Perché è stato aggiunto/modificato**: il branch lavora di più sul ruolo admin creato automaticamente e sul caricamento dei permessi in `me`.
- **Come si collega al resto del sistema**: viene letto da `AuthController`, dai test auth e dai job/seed che creano il profilo locale admin.

### `app/Services/TenantRegistrationService.php`

- **Cosa fa**: orchestra la registrazione di un tenant, la creazione dell'identità globale, del record tenant, del dominio, della membership e dell'OTP iniziale.
- **Perché è stato aggiunto/modificato**: centralizza il provisioning del tenant e aggiunge rollback manuale per i casi in cui la registrazione fallisce a metà, soprattutto su piano dedicated.
- **Come si collega al resto del sistema**: è il punto di ingresso per `TenantRegistrationController`, alimenta i job `CreateTenantAdminUser` e `SendOtpEmail`, scrive su `tenants`, `domains`, `tenant_memberships` e `global_identities`, e dipende dalla configurazione tenancy/database.

### `app/Services/UserRegistrationService.php`

- **Cosa fa**: registra un utente dentro un tenant già esistente, creando o riutilizzando la `GlobalIdentity` e aggiungendo una membership pending.
- **Perché è stato aggiunto/modificato**: implementa il flusso di richiesta accesso a un workspace invece del classico signup "aperto".
- **Come si collega al resto del sistema**: dopo la membership pending inizializza tenancy, cerca gli admin locali e dispatcha `NotifyAdminNewUser` con il link al pannello.

### `app/Http/Controllers/Api/V1/OtpController.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: gestisce verifica OTP, richiesta OTP, elenco tenant accessibili, selezione tenant e, nel worktree attuale, anche l'handoff `storeTokens` via Redis e redirect al tenant.
- **Perché è stato aggiunto/modificato**: introduce il flusso di login globale basato su OTP e il passaggio di token al tenant domain tramite chiave monouso in Redis.
- **Come si collega al resto del sistema**: usa `GlobalIdentity`, `OtpCode`, `Tenant`, `User`, `JwtService`, `VerifyIdentityToken`, `RequestOtpRequest`, `VerifyOtpRequest`, `SelectTenantRequest`, la coda email OTP e la route `store-tokens`.

### `app/Http/Middleware/VerifyIdentityToken.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: valida un bearer token e si assicura che sia un identity token, non un access token tenant-scoped.
- **Perché è stato aggiunto/modificato**: serve a proteggere gli endpoint del flusso OTP globale e a trasportare l'`identity_payload` nella request.
- **Come si collega al resto del sistema**: è registrato come alias `identity.auth` in bootstrap e protegge le rotte `tenants` e `selectTenant`.

### `app/Http/Requests/V1/RequestOtpRequest.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: valida la richiesta di un OTP imponendo `email` obbligatoria e valida.
- **Perché è stato aggiunto/modificato**: standardizza l'input del flusso OTP e sposta la validazione fuori dal controller.
- **Come si collega al resto del sistema**: viene usato da `OtpController::requestOtp`.

### `app/Http/Requests/V1/SelectTenantRequest.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: valida il tenant selezionato dall'utente dopo la login globale.
- **Perché è stato aggiunto/modificato**: garantisce che la selezione del workspace passi da una request validata.
- **Come si collega al resto del sistema**: viene usato da `OtpController::selectTenant` insieme al middleware `identity.auth`.

### `app/Http/Requests/V1/VerifyOtpRequest.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: valida `email` e OTP a 6 cifre nel flusso di verifica email.
- **Perché è stato aggiunto/modificato**: separa la validazione del codice dal controller e rende il contratto dell'endpoint esplicito.
- **Come si collega al resto del sistema**: viene usato da `OtpController::verify`.

### `bootstrap/app.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: registra il file `routes/api_v1.php` con prefisso `api/v1` e mappa gli alias middleware `jwt.auth` e `identity.auth`.
- **Perché è stato aggiunto/modificato**: abilita il wiring del nuovo flusso auth/OTP e dei middleware personalizzati del branch.
- **Come si collega al resto del sistema**: è il punto in cui Laravel carica il routing e collega i middleware alle rotte API.

### `routes/api_v1.php` *(worktree corrente, non ancora committato)*

- **Cosa fa**: espone tutte le rotte V1 del dominio auth/tenant, incluse registrazione tenant, OTP, selezione tenant, login, refresh, `me` e `store-tokens`.
- **Perché è stato aggiunto/modificato**: organizza il nuovo flusso di autenticazione globale + tenant-scoped in un unico file di route versionato.
- **Come si collega al resto del sistema**: usa `identity.auth`, `jwt.auth`, `InitializeTenancyByDomain` e `PreventAccessFromCentralDomains` per separare bene il traffico centrale da quello del tenant.

### `config/database.php`

- **Cosa fa**: aggiunge una connessione `shared` dedicata al database condiviso dei tenant del piano base.
- **Perché è stato aggiunto/modificato**: il branch gestisce due modelli di provisioning, shared e dedicated, e serve una connessione esplicita per i dati condivisi.
- **Come si collega al resto del sistema**: viene usata dal tenancy layer, dai test e dal `TenantRegistrationService` quando crea o valida i tenant.

### `composer.json`

- **Cosa fa**: dichiara le dipendenze runtime e di sviluppo del progetto.
- **Perché è stato aggiunto/modificato**: il branch introduce JWT, Predis e tenancy, e allinea lo stack di test e sviluppo alla nuova architettura.
- **Come si collega al resto del sistema**: abilita `firebase/php-jwt` per i token, `predis/predis` per Redis e `stancl/tenancy` per la gestione multi-tenant.

### `composer.lock`

- **Cosa fa**: blocca le versioni esatte delle dipendenze installate.
- **Perché è stato aggiunto/modificato**: si è aggiornato insieme a `composer.json` per fissare i pacchetti introdotti dal branch.
- **Come si collega al resto del sistema**: garantisce che runtime e test usino le stesse versioni delle librerie di tenancy, JWT e Redis.

### `phpunit.xml`

- **Cosa fa**: configura l'ambiente di test, compresi DB, queue, mail e Redis.
- **Perché è stato aggiunto/modificato**: i nuovi test toccano tenant provisioning, Redis, queue e validazione auth, quindi serve un setup coerente.
- **Come si collega al resto del sistema**: guida `php artisan test` e isola il comportamento di runtime in ambiente `testing`.

### `resources/views/emails/otp.blade.php`

- **Cosa fa**: renderizza il template HTML del codice OTP.
- **Perché è stato aggiunto/modificato**: `SendOtpEmail` ha bisogno di un corpo mail da serializzare in coda.
- **Come si collega al resto del sistema**: viene usata dal job OTP e completa il flow di verifica email.

### `resources/views/emails/welcome.blade.php`

- **Cosa fa**: renderizza la mail di benvenuto per un nuovo workspace o utente abilitato.
- **Perché è stato aggiunto/modificato**: accompagna il provisioning del tenant e il kickoff dell'onboarding.
- **Come si collega al resto del sistema**: viene usata da `SendWelcomeEmail`.

### `resources/views/emails/notify-admin-new-user.blade.php`

- **Cosa fa**: renderizza la notifica agli admin per una richiesta di accesso in pending.
- **Perché è stato aggiunto/modificato**: serve al flusso di approvazione utenti nel tenant.
- **Come si collega al resto del sistema**: viene usata da `NotifyAdminNewUser` e include il link al pannello.

## File presenti nel worktree corrente ma non ancora committati

Questi file fanno parte dello stato attuale del branch in workspace, ma non risultano ancora nei commit rispetto a `main`:

- `app/Http/Controllers/Api/V1/AuthController.php`
- `app/Http/Controllers/Api/V1/OtpController.php`
- `app/Http/Middleware/VerifyIdentityToken.php`
- `app/Http/Requests/V1/RequestOtpRequest.php`
- `app/Http/Requests/V1/SelectTenantRequest.php`
- `app/Http/Requests/V1/VerifyOtpRequest.php`
- `app/Services/TenantRegistrationService.php`
- `bootstrap/app.php`
- `routes/api_v1.php`

## Test aggiunti o aggiornati

### `tests/Feature/Api/V1/Auth/LoginTest.php`

- `test_user_can_login_with_valid_credentials`: verifica che un utente valido ottenga 200, payload JSON corretto e cookie `access_token`/`refresh_token`.
- `test_login_fails_with_wrong_password`: verifica che una password errata produca 401 e nessun cookie.
- `test_login_requires_validation`: verifica che i campi obbligatori siano validati con 422.
- `test_login_fails_if_user_does_not_belong_to_tenant`: verifica che un utente non autorizzato sul tenant riceva 403.
- `test_login_fails_if_membership_is_not_accepted`: verifica che una membership non accettata blocchi il login con 403.
- `test_login_fails_if_local_profile_is_missing`: verifica che l'assenza del profilo locale restituisca 500.

### `tests/Feature/Api/V1/Auth/MeEndpointTest.php`

- `test_user_can_get_their_profile_with_valid_access_token`: verifica che un access token valido consenta di ottenere user, tenant, role e permissions.
- `test_me_fails_without_any_token`: verifica che l'endpoint rifiuti richieste senza token con 401.
- `test_me_fails_if_refresh_token_is_used_instead_of_access`: verifica che un refresh token non possa essere usato come access token.
- `test_me_fails_on_cross_tenant_token_forgery`: verifica che un token valido ma per un tenant diverso venga bloccato.
- `test_me_fails_if_local_profile_is_missing`: verifica che la mancanza del profilo locale restituisca 404.

### `tests/Feature/Api/V1/Auth/RefreshTokenTest.php`

- `test_user_can_refresh_token_with_valid_cookie`: verifica che un refresh token valido generi un nuovo access token e il cookie relativo.
- `test_refresh_fails_without_refresh_cookie`: verifica che l'assenza del cookie di refresh produca 401.
- `test_refresh_fails_with_invalid_refresh_cookie`: verifica che un cookie di refresh inventato produca 401.
- `test_refresh_fails_if_membership_is_not_accepted`: verifica che una membership non accettata blocchi il refresh con 403.
- `test_refresh_fails_if_local_profile_is_missing`: verifica che la mancanza del profilo locale sul tenant produca 500.

### `tests/Feature/Api/V1/Auth/UserRegistrationTest.php`

- `test_user_can_register_with_new_email`: verifica che la registrazione crei global identity, hash password e membership pending.
- `test_user_with_existing_global_identity_gets_only_membership_created`: verifica il riuso della global identity già esistente.
- `test_registration_fails_if_email_is_already_accepted_in_this_tenant`: verifica che una mail già accettata nel tenant venga rifiutata.
- `test_registration_fails_if_email_is_already_pending_in_this_tenant`: verifica che una mail già pending nel tenant venga rifiutata.
- `test_registration_requires_all_fields`: verifica la validazione dei campi richiesti.
- `test_registration_fails_with_invalid_email_format`: verifica il controllo del formato email.
- `test_registration_fails_if_password_is_too_short`: verifica la lunghezza minima della password.

### `tests/Feature/Services/TenantRegistrationServiceTest.php`

- `test_it_creates_a_global_identity_during_registration`: verifica la creazione della global identity e l'hash corretto della password.
- `test_shared_plan_creates_tenant_without_custom_db_credentials`: verifica che il piano shared non generi credenziali DB personalizzate.
- `test_dedicated_plan_creates_tenant_with_custom_db_credentials`: verifica che il piano dedicated generi database user/password dedicati.
- `test_shared_plan_dispatches_seed_and_admin_jobs`: verifica che sul piano shared venga dispatchato il job pipeline di seed.
- `test_dedicated_plan_dispatches_full_infrastructure_jobs`: verifica che sul piano dedicated vengano dispatchati create database, mysql user, migrate e seed.
- `test_it_creates_domain_and_membership_during_registration`: verifica la creazione del dominio tenant e della membership accepted.
- `test_it_physically_creates_the_tenant_database_in_mysql`: verifica la creazione fisica del database tenant su MySQL.
- `test_shared_plan_creates_local_user_in_tenant_database`: verifica la creazione dell'utente locale nel DB tenant shared.
- `test_dedicated_plan_creates_local_user_in_tenant_database`: verifica la creazione dell'utente locale nel DB tenant dedicated.
- `test_it_rolls_back_data_if_dedicated_registration_fails`: verifica il rollback manuale completo se la registrazione dedicated fallisce.
- `test_it_rolls_back_everything_if_dedicated_seeding_fails`: verifica il rollback se fallisce il seeding del tenant dedicato.
- `test_it_rolls_back_data_if_shared_registration_fails`: verifica che il piano shared annulli identità, tenant e dominio se il provisioning fallisce.

## Note operative

- Il routing reale della V1 è registrato con prefisso `api/v1` in `bootstrap/app.php`, quindi gli endpoint finali includono quel prefisso.
- Il flusso OTP globale usa Redis come handoff temporaneo tra selezione tenant e settaggio cookie sul tenant domain.
- I cookie auth ora sono letti dalla configurazione sessione, non hardcoded nel controller.
- La configurazione `shared` in `config/database.php` è il perno per i test e per i piani senza database dedicato.
