<?php

namespace App\Services;

use App\Exceptions\DatabaseAlreadyExistsException;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\User as TenantUser;
use App\Models\Tenant\Role as TenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantRegistrationService
{
    public function register(array $data): Tenant
    {
        $identity = $this->createGlobalIdentity($data);
    }

    private function createGlobalIdentity(array $data) : GlobalIdentity {
        return Globalidentity::create([
            "name" => $data["adminName"],
            "email" => $data["adminEmail"],
            "password" => Hash::make($data["adminPassword"]),
        ]);
    }
    
    private function createTenantRecord(array $data, $plan) {}

    private function createTenantDomain(Tenant $tenant, string $subdomain) {}
    
    private function linkIdentityToTenant($identity, Tenant $tenant) {}
    
    private function setupTenantDatabase(Tenant $tenant, $identity) {}
}