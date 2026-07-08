<?php

namespace App\Http\Requests\PayrollPeriod;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}