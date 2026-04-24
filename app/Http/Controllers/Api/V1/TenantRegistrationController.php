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

        return response()->json([
            'message' => 'Tenant creato con successo.',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domains->first()->domain
            ]
        ], 201); // 201 Created come da standard
    }
}