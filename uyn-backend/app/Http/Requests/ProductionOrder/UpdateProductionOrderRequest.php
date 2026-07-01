<?php

namespace App\Http\Requests\ProductionOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        if (array_key_exists('location', $input)) {
            $prepared['location'] = is_string($input['location'])
                && trim($input['location']) !== ''
                    ? trim($input['location'])
                    : null;
        }

        if (array_key_exists('priority', $input)) {
            $prepared['priority'] = Str::lower(
                trim((string) $input['priority'])
            );
        }

        if (array_key_exists('notes', $input)) {
            $prepared['notes'] = is_string($input['notes'])
                && trim($input['notes']) !== ''
                    ? trim($input['notes'])
                    : null;
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'start_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'end_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'priority' => [
                'sometimes',
                'required',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.in' => 'La prioridad seleccionada no es válida.',
        ];
    }
}