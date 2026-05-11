<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SelectTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Il tenant è obbligatorio',
        ];
    }
}