<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollPeriodReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_details' => [
                'nullable',
                'boolean',
            ],

            'payment_type' => [
                'nullable',
                Rule::in(['piecework', 'fixed', 'mixed', 'all']),
            ],

            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ],
        ];
    }
}