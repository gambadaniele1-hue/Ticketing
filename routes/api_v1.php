<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TenantRegistrationController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\TenantController;
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
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/register', [AuthController::class, 'register']);

        // ---------------------------------------------------------------------
        // Auth tenant — route protette (richiede access token JWT)
        // ---------------------------------------------------------------------
        Route::middleware('jwt.auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            // Route::post('/logout', [AuthController::class, 'logout']);
        });
    });
});