<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Global\TenantResource;

class TenantController extends Controller
{
    public function info(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'data' => new TenantResource($tenant),
        ]);
    }
}