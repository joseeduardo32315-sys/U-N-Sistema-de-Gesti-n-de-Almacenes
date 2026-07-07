<?php

namespace App\Http\Requests\PieceworkRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPieceworkRateRequest extends FormRequest
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

            'operation_process_id' => [
                'nullable',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive', 'all']),
            ],

            'active_on' => [
                'nullable',
                'date',
            ],

            'search' => [
                'nullable',
                'string',
                'max:150',
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