<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint pubblico
    }

    public function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'alpha_dash', 'unique:domains,domain'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'unique:global_identities,email'],
            'adminPassword' => ['required', 'string', 'min:8'],
            'planId' => ['required', 'exists:plans,id'],
        ];
    }
}