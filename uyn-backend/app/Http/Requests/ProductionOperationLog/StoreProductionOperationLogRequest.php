<?php

namespace App\Http\Requests\ProductionOperationLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOperationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge([
                'notes' => is_string($this->input('notes'))
                    && trim((string) $this->input('notes')) !== ''
                        ? trim((string) $this->input('notes'))
                        : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('status', 'active'),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'production_movement_id' => ['prohibited'],
            'operation_process_id' => ['prohibited'],
            'quantity_processed' => ['prohibited'],
            'status' => ['prohibited'],
            'start_time' => ['prohibited'],
            'end_time' => ['prohibited'],
            'payout_amount' => ['prohibited'],
            'payout_snapshot' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' =>
                'Debes seleccionar al trabajador responsable.',

            'employee_id.exists' =>
                'El trabajador seleccionado no existe o está inactivo.',
        ];
    }
}