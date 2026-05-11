<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'email è obbligatoria',
            'email.email' => 'L\'email non è valida',
            'code.required' => 'Il codice è obbligatorio',
            'code.size' => 'Il codice deve essere di 6 caratteri',
        ];
    }
}