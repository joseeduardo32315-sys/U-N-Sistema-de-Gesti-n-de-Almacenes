<?php

namespace App\Http\Requests\PayrollPeriod;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['payment_date', 'notes'] as $field) {
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
            'payment_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'code' => ['prohibited'],
            'frequency' => ['prohibited'],
            'start_date' => ['prohibited'],
            'end_date' => ['prohibited'],
            'status' => ['prohibited'],
            'generated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'generated_by' => ['prohibited'],
            'closed_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $period = $this->route('payroll_period');

            if (! $period instanceof PayrollPeriod) {
                return;
            }

            if ($period->status !== 'draft') {
                $validator->errors()->add(
                    'status',
                    'Solo puedes modificar periodos en estado borrador.'
                );
            }

            $paymentDate = $this->input(
                'payment_date',
                $period->payment_date?->toDateString()
            );

            if (
                $paymentDate !== null
                && $paymentDate < $period->end_date?->toDateString()
            ) {
                $validator->errors()->add(
                    'payment_date',
                    'La fecha de pago no puede ser anterior al fin del periodo.'
                );
            }
        });
    }
}