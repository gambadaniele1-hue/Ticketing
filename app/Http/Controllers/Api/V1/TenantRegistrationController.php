<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreTenantRequest;
use App\Services\TenantRegistrationService;
use Illuminate\Http\JsonResponse;

class TenantRegistrationController extends Controller
{
    protected TenantRegistrationService $registrationService;

    public function __construct(TenantRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        // Deleghiamo al service passando i dati validati
        $tenant = $this->registrationService->register($request->validated());

        $domain = $tenant->domains->first()->domain;

        return response()->json([
            'message' => 'Tenant creato con successo. Benvenuto a bordo!',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    // Il dominio per intero
                    'domain' => $domain,
                    // Un URL già pronto che il frontend può usare per il bottone "Vai al tuo spazio"
                    'login_url' => 'http://' . $domain . '/login',
                    // Utile per far vedere un riepilogo "Abbiamo inviato un'email a..."
                    'admin_email' => $request->adminEmail,
                    // Data di creazione formattata
                    'created_at' => $tenant->created_at->toIso8601String(),
                ]
            ]
        ], 201); // 201 Created come da standard
    }
}