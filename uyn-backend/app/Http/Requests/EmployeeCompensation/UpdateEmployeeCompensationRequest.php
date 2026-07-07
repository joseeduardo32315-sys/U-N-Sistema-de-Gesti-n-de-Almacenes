<?php

namespace App\Http\Requests\EmployeeCompensation;

use App\Models\EmployeeCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        if ($this->has('effective_to') && $this->input('effective_to') === '') {
            $prepared['effective_to'] = null;
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
            'effective_to' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'employee_id' => ['prohibited'],
            'payment_type' => ['prohibited'],
            'payment_frequency' => ['prohibited'],
            'fixed_amount' => ['prohibited'],
            'effective_from' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $compensation = $this->route('employee_compensation');

            if (! $compensation instanceof EmployeeCompensation) {
                return;
            }

            $effectiveTo = $this->input(
                'effective_to',
                $compensation->effective_to?->toDateString()
            );

            $effectiveFrom = $compensation->effective_from?->toDateString();

            if (
                $effectiveTo !== null
                && $effectiveFrom !== null
                && $effectiveTo < $effectiveFrom
            ) {
                $validator->errors()->add(
                    'effective_to',
                    'La fecha final no puede ser anterior a la fecha inicial.'
                );
            }
        });
    }
}