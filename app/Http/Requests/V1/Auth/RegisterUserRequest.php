<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Il nome è obbligatorio.',
            'email.required'    => 'L\'indirizzo email è obbligatorio.',
            'email.email'       => 'L\'indirizzo email non è valido.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve essere di almeno :min caratteri.',
        ];
    }
}
