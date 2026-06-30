<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        // Valida el campo 'name' y lo prepara para la validación
        if (array_key_exists('name', $input)) {
            $prepared['name'] = trim((string) $input['name']);
        }

        // Valida el campo 'worker_type' y lo prepara para la validación
        if (array_key_exists('worker_type', $input)) {
            $prepared['worker_type'] = Str::lower(
                trim((string) $input['worker_type'])
            );
        }

        // Valida el campo 'phone' y lo prepara para la validación
        if (array_key_exists('phone', $input)) {
            $prepared['phone'] = trim((string) $input['phone']);
        }

        // Valida el campo 'notes' y lo prepara para la validación
        if (array_key_exists('notes', $input)) {
            $notes = $input['notes'];

            $prepared['notes'] = $this->filled('notes')
                ? trim((string) $notes)
                : null;
        }

        $this->merge($prepared);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'area_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'worker_type' => [
                'sometimes',
                'required',
                Rule::in(['internal', 'external']),
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:30',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'area_id.exists' => 'El área seleccionada no es válida.',
            'worker_type.in' => 'El tipo de trabajador seleccionado no es válido.',
            'phone.max' => 'El número de teléfono no puede tener más de 30 caracteres.',
            'notes.max' => 'Las notas no pueden tener más de 2000 caracteres.',
        ];
    }
}
