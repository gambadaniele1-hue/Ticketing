<?php

namespace App\Services;

use App\Jobs\NotifyAdminNewUser;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Jobs\SendWelcomeEmail;

class UserRegistrationService
{
    public function register(array $data, Tenant $tenant)
    {
        $identity = GlobalIdentity::where('email', $data['email'])->first();

        if ($identity) {
            $existingMembership = $identity->memberships()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($existingMembership) {
                throw ValidationException::withMessages([
                    'email' => 'Questo indirizzo email è già registrato in questo workspace.',
                ]);
            }
        } else {
            $identity = GlobalIdentity::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        }

        $membership = $tenant->memberships()->create([
            'global_user_id' => $identity->id,
            'state' => 'pending',
        ]);

        // Entriamo nel tenant per cercare gli admin
        tenancy()->initialize($tenant);

        // Troviamo il ruolo base (es. Customer/Agent) da assegnare al nuovo utente
        $defaultRole = Role::where('name', 'Customer')->first();

        if (!$defaultRole) {
            tenancy()->end();
            return response()->json([
                'message' => 'Ruolo di default non trovato nel workspace',
                'error_code' => 'DEFAULT_ROLE_NOT_FOUND',
            ], 500);
        }

        // Creiamo il profilo locale nel DB tenant
        User::create([
            'global_user_id' => $identity->id,
            'role_id' => $defaultRole?->id,
        ]);

        $adminUsers = User::whereHas('role', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        foreach ($adminUsers as $adminUser) {
            // Recuperiamo l'identità globale per avere email e nome
            $globalIdentity = GlobalIdentity::find($adminUser->global_user_id);

            if ($globalIdentity) {
                $loginUrl = 'http://' . $tenant->domains->first()->domain . '/login';

                NotifyAdminNewUser::dispatch(
                    $globalIdentity->email,
                    $globalIdentity->name,
                    $identity->name,
                    $identity->email,
                    $tenant->name,
                    $loginUrl,
                    now()->format('d/m/Y H:i'),
                    $tenant->id, // ← aggiungi
                );
            }
        }

        // Usciamo dal tenant
        tenancy()->end();

        return $membership;
    }
}
