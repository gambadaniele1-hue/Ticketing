<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TenantRegistrationController;

// Tutte le rotte qui dentro avranno automaticamente il prefisso /api/v1 
// (lo configuriamo nel prossimo step)

Route::post('/register', [TenantRegistrationController::class, 'store']);

