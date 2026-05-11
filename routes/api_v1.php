<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TenantRegistrationController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Api\V1\OtpController;

// Tutte le rotte qui dentro avranno automaticamente il prefisso /api/v1 
// (lo configuriamo nel prossimo step)

Route::post('/register-tenant', [TenantRegistrationController::class, 'store']);


Route::post('/auth/otp/verify', [OtpController::class, 'verify']);
Route::post('/auth/global-login/request-otp', [OtpController::class, 'requestOtp']);

Route::middleware('identity.auth')->group(function () {
    Route::get('/auth/global-login/tenants', [OtpController::class, 'tenants']);
    Route::post('/auth/global-login/select-tenant', [OtpController::class, 'selectTenant']); // ← aggiungi
});

Route::middleware([
        // 1. Legge il dominio (es: wayne.app.com) e si connette al DB di quel tenant
    InitializeTenancyByDomain::class,

        // 2. Blocca chi cerca di chiamare la rotta dal dominio principale (es: app.com/api/v1/login)
    PreventAccessFromCentralDomains::class,
])->prefix('/auth')->group(function () {

    // Rotte pubbliche (solo per chi non è ancora loggato)

    Route::get('/store-tokens', [AuthController::class, 'storeTokens']);

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/register', [AuthController::class, 'register']);

    // Rotte protette dal tuo JWT (chi è loggato)
    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        //Route::post('/logout', [AuthController::class, 'logout']);
    });

});