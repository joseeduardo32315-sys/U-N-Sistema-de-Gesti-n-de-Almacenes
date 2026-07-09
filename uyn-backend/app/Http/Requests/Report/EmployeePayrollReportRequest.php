<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeePayrollReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ],

            'from' => [
                'bail',
                'required',
                'date',
            ],

            'to' => [
                'bail',
                'required',
                'date',
                'after_or_equal:from',
            ],

            'payment_type' => [
                'nullable',
                Rule::in(['piecework', 'fixed', 'mixed', 'all']),
            ],

            'status' => [
                'nullable',
                Rule::in(['generated', 'paid', 'all']),
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