<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionLossReportRequest extends FormRequest
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
                    'open',
                    'resolved',
                    'cancelled',
                    'all',
                ]),
            ],

            'group_by' => [
                'nullable',
                Rule::in([
                    'garment_cut',
                    'process',
                    'responsible_employee',
                ]),
            ],

            'garment_cut_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_cuts', 'id'),
            ],

            'process_id' => [
                'nullable',
                'integer',
                Rule::exists('processes', 'id'),
            ],

            'responsible_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ],
        ];
    }
}