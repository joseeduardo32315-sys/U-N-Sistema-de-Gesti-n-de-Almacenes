<?php

namespace App\Http\Requests\EmployeeCompensation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['payment_type', 'payment_frequency'] as $field) {
            if ($this->filled($field)) {
                $prepared[$field] = Str::lower(
                    trim((string) $this->input($field))
                );
            }
        }

        foreach (['fixed_amount', 'effective_to'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $prepared[$field] = null;
            }
        }

        if ($this->has('notes')) {
            $prepared['notes'] = is_string($this->input('notes'))
                && trim((string) $this->input('notes')) !== ''
                    ? trim((string) $this->input('notes'))
                    : null;
        }

        $this->merge($prepared);
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

            'payment_type' => [
                'bail',
                'required',
                Rule::in(['piecework', 'fixed']),
            ],

            'payment_frequency' => [
                'nullable',
                Rule::in(['weekly', 'biweekly', 'monthly']),
            ],

            'fixed_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],

            'effective_from' => [
                'bail',
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paymentType = $this->input('payment_type');

            if ($paymentType === 'fixed') {
                if (! $this->filled('payment_frequency')) {
                    $validator->errors()->add(
                        'payment_frequency',
                        'Debes indicar la frecuencia del pago fijo.'
                    );
                }

                if ($this->input('fixed_amount') === null) {
                    $validator->errors()->add(
                        'fixed_amount',
                        'Debes indicar el monto fijo del periodo.'
                    );
                }

                return;
            }

            if ($this->filled('payment_frequency')) {
                $validator->errors()->add(
                    'payment_frequency',
                    'La frecuencia solo aplica para trabajadores con pago fijo.'
                );
            }

            if ($this->input('fixed_amount') !== null) {
                $validator->errors()->add(
                    'fixed_amount',
                    'El monto fijo solo aplica para trabajadores con pago fijo.'
                );
            }
        });
    }
}