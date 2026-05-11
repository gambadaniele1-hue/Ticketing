<?php

namespace App\Services;

use App\Jobs\NotifyAdminNewUser;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Jobs\SendWelcomeEmail;

class UserRegistrationService
{
    public function register(array $data, Tenant $tenant): TenantMembership
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

        $adminUsers = User::whereHas('role', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        foreach ($adminUsers as $adminUser) {
            // Recuperiamo l'identità globale per avere email e nome
            $globalIdentity = GlobalIdentity::find($adminUser->global_user_id);

            if ($globalIdentity) {
                $loginUrl = 'http://' . $tenant->domains->first()->domain . '/login';

                NotifyAdminNewUser::dispatch(
                    $globalIdentity->email,        // email admin
                    $globalIdentity->name,         // nome admin
                    $identity->name,               // nome nuovo utente
                    $identity->email,              // email nuovo utente
                    $tenant->name,                 // nome workspace
                    $loginUrl,                     // link pannello
                    now()->format('d/m/Y H:i'),   // data richiesta
                );
            }
        }

        // Usciamo dal tenant
        tenancy()->end();

        return $membership;
    }
}
