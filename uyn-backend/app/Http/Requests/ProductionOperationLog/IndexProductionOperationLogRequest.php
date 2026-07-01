<?php

namespace App\Http\Requests\ProductionOperationLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionOperationLogRequest extends FormRequest
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

            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'with_incident',
                ]),
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