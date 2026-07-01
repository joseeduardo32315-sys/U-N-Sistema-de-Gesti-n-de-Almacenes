<?php

namespace App\Http\Requests\ProductionOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'order_code' => Str::upper(
                trim((string) $this->input('order_code'))
            ),

            'location' => $this->filled('location')
                ? trim((string) $this->input('location'))
                : null,

            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ];

        if ($this->filled('priority')) {
            $prepared['priority'] = Str::lower(
                trim((string) $this->input('priority'))
            );
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'order_code' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('production_orders', 'order_code'),
            ],

            'location' => [
                'nullable',
                'string',
                'max:150',
            ],

            'start_date' => [
                'bail',
                'required',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'priority' => [
                'nullable',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_code.required' => 'El folio de la orden es obligatorio.',
            'order_code.unique' => 'El folio de la orden ya está registrado.',
            'order_code.regex' => 'El folio solo puede contener letras, números, puntos, guiones y guiones bajos.',

            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.after_or_equal' => 'La fecha estimada no puede ser anterior a la fecha de inicio.',

            'priority.in' => 'La prioridad seleccionada no es válida.',
        ];
    }
}