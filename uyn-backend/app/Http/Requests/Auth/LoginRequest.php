<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => trim((string) $this->input('login')),
            'device_name' => $this->filled('device_name')
                ? trim((string) $this->input('device_name'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'login' => ['bail', 'required', 'string', 'max:120'],
            'password' => ['bail', 'required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'El usuario o correo electrónico es obligatorio.',
            'login.max' => 'El usuario o correo no puede exceder 120 caracteres.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.max' => 'La contraseña no puede exceder 255 caracteres.',

            'device_name.max' => 'El nombre del dispositivo no puede exceder 100 caracteres.',
        ];
    }
}