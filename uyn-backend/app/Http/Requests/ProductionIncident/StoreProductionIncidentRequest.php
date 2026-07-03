<?php

namespace App\Http\Requests\ProductionIncident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductionIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        if ($this->filled('incident_type')) {
            $prepared['incident_type'] = Str::lower(
                trim((string) $this->input('incident_type'))
            );
        }

        foreach (['description', 'notes'] as $field) {
            if ($this->has($field)) {
                $prepared[$field] = is_string($this->input($field))
                    && trim((string) $this->input($field)) !== ''
                        ? trim((string) $this->input($field))
                        : null;
            }
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'production_movement_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('production_movements', 'id'),
            ],

            'incident_type' => [
                'bail',
                'required',
                Rule::in([
                    'damage',
                    'loss',
                    'quality',
                    'delay',
                    'other',
                ]),
            ],

            'quantity_affected' => [
                'bail',
                'required',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'description' => [
                'bail',
                'required',
                'string',
                'max:3000',
            ],

            'responsible_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('status', 'active'),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'garment_cut_id' => ['prohibited'],
            'status' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'resolved_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('incident_type');

            $quantity = (int) $this->input(
                'quantity_affected',
                0
            );

            if (
                in_array(
                    $type,
                    ['damage', 'loss', 'quality'],
                    true
                )
                && $quantity < 1
            ) {
                $validator->errors()->add(
                    'quantity_affected',
                    'Las incidencias de daño, pérdida o calidad deben afectar al menos una pieza.'
                );
            }

            if ($type === 'delay' && $quantity !== 0) {
                $validator->errors()->add(
                    'quantity_affected',
                    'Una incidencia de retraso debe registrarse con cantidad afectada igual a cero.'
                );
            }
        });
    }
}