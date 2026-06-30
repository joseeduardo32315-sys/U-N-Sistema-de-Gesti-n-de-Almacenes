<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
        $this->merge([
            'name' => trim((string) $this->input('name')),

            'worker_type' => Str::lower(
                trim((string) $this->input('worker_type'))
            ),

            'phone' => trim((string) $this->input('phone')),

            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
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
                'bail',
                'required',
                'string',
                'max:150',
            ],

            'area_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'worker_type' => [
                'bail',
                'required',
                Rule::in(['internal', 'external']),
            ],

            'phone' => [
                'bail',
                'required',
                'string',
                'max:30',
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del empleado es obligatorio.',
            'area_id.required' => 'El área del empleado es obligatoria.',
            'area_id.exists' => 'El área seleccionada no es válida.',
            'worker_type.required' => 'El tipo de trabajador es obligatorio.',
            'worker_type.in' => 'El tipo de trabajador seleccionado no es válido.',
            'phone.required' => 'El teléfono del empleado es obligatorio.',
            'notes.max' => 'Las notas del empleado no pueden exceder los 2000 caracteres.',
        ];
    }
}
