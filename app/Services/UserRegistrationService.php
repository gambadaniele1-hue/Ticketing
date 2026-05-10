<?php

namespace App\Services;

use App\Models\Global\GlobalIdentity;
use App\Models\Global\Tenant;
use App\Models\Global\TenantMembership;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        }

        $membership = $tenant->memberships()->create([
            'global_user_id' => $identity->id,
            'state'          => 'pending',
        ]);

        // TODO: pubblicare job su Redis per invio mail di conferma
        // { to: $data['email'], subject: 'Conferma registrazione', html: '...', text: '...' }

        return $membership;
    }
}
