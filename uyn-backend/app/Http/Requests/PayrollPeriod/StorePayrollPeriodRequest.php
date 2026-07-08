<?php

namespace App\Http\Requests\PayrollPeriod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['code', 'frequency'] as $field) {
            if ($this->filled($field)) {
                $prepared[$field] = Str::lower(
                    trim((string) $this->input($field))
                );
            }
        }

        if ($this->filled('code')) {
            $prepared['code'] = Str::upper(
                trim((string) $this->input('code'))
            );
        }

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
            'code' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('payroll_periods', 'code'),
            ],

            'frequency' => [
                'bail',
                'required',
                Rule::in(['weekly', 'biweekly', 'monthly']),
            ],

            'start_date' => [
                'bail',
                'required',
                'date',
            ],

            'end_date' => [
                'bail',
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'payment_date' => [
                'nullable',
                'date',
                'after_or_equal:end_date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'status' => ['prohibited'],
            'generated_at' => ['prohibited'],
            'closed_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'generated_by' => ['prohibited'],
            'closed_by' => ['prohibited'],
        ];
    }
}