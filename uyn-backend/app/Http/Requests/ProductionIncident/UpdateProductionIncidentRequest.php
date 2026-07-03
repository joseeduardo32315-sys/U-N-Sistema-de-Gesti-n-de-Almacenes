<?php

namespace App\Http\Requests\ProductionIncident;

use App\Models\ProductionIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductionIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

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
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:3000',
            ],

            'quantity_affected' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'responsible_employee_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('status', 'active'),
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'garment_cut_id' => ['prohibited'],
            'production_movement_id' => ['prohibited'],
            'incident_type' => ['prohibited'],
            'status' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'resolved_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('quantity_affected')) {
                return;
            }

            $incident = $this->route(
                'production_incident'
            );

            if (
                ! $incident instanceof ProductionIncident
                || ! in_array(
                    $incident->incident_type,
                    ['damage', 'loss', 'quality'],
                    true
                )
            ) {
                return;
            }

            if ((int) $this->input('quantity_affected') < 1) {
                $validator->errors()->add(
                    'quantity_affected',
                    'Las incidencias de daño, pérdida o calidad deben afectar al menos una pieza.'
                );
            }
        });
    }
}