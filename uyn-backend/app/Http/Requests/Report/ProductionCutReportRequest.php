<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionCutReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => [
                'nullable',
                'date',
            ],

            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'registered',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'all',
                ]),
            ],

            'current_area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'garment_model_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_models', 'id'),
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