<?php

namespace App\Http\Requests\ProductionIncident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexProductionIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['incident_type', 'status'] as $field) {
            if ($this->filled($field)) {
                $prepared[$field] = Str::lower(
                    trim((string) $this->input($field))
                );
            }
        }

        if ($this->filled('search')) {
            $prepared['search'] = trim(
                (string) $this->input('search')
            );
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'garment_cut_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_cuts', 'id'),
            ],

            'production_movement_id' => [
                'nullable',
                'integer',
                Rule::exists('production_movements', 'id'),
            ],

            'responsible_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ],

            'incident_type' => [
                'nullable',
                Rule::in([
                    'damage',
                    'loss',
                    'quality',
                    'delay',
                    'other',
                ]),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'open',
                    'resolved',
                    'cancelled',
                ]),
            ],

            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}