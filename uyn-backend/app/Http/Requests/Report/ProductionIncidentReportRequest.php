<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionIncidentReportRequest extends FormRequest
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

            'incident_type' => [
                'nullable',
                Rule::in([
                    'damage',
                    'loss',
                    'quality',
                    'delay',
                    'other',
                    'all',
                ]),
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

            'garment_cut_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_cuts', 'id'),
            ],

            'production_movement_id' => [
                'nullable',
                'integer',
                Rule::exists('production_movements', 'id'),
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

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}