<?php

namespace App\Http\Requests\PieceworkRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePieceworkRateRequest extends FormRequest
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
            'employee_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('status', 'active'),
            ],

            'operation_process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'amount_per_piece' => [
                'bail',
                'required',
                'numeric',
                'min:0.0001',
                'max:99999999.9999',
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
}