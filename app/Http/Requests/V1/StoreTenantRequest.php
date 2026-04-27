<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint pubblico per la registrazione
    }

    /**
     * Prepara i dati PRIMA che vengano validati.
     * Ottimo per sanificare gli input come il sottodominio.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain')) {
            $this->merge([
                'subdomain' => strtolower($this->subdomain),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            // Validazione DNS-safe: solo minuscole, numeri e trattini (no underscore)
            'subdomain' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tenants,id'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'unique:global_identities,email'],
            'adminPassword' => ['required', 'string', 'min:8'],
            'planId' => ['required', 'exists:plans,id'],
        ];
    }

    /**
     * Messaggi di errore personalizzati per il frontend.
     */
    public function messages(): array
    {
        return [
            'subdomain.unique' => 'Questo sottodominio è già in uso. Scegline un altro.',
            'subdomain.regex' => 'Il sottodominio può contenere solo lettere minuscole, numeri e trattini, senza iniziare o finire con un trattino.',
            'adminEmail.unique' => 'Questa email è già registrata nel sistema.',
            'planId.exists' => 'Il piano selezionato non è valido o non esiste.',
        ];
    }
}