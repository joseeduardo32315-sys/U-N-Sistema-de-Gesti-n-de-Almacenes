<?php

namespace App\Http\Requests\EmployeeCompensation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeCompensationRequest extends FormRequest
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

            'payment_type' => [
                'nullable',
                Rule::in(['piecework', 'fixed']),
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive', 'all']),
            ],

            'active_on' => [
                'nullable',
                'date',
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