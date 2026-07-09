<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionMovementReportRequest extends FormRequest
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

            'operation_process_id' => [
                'nullable',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'from_area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'to_area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'target_type' => [
                'nullable',
                Rule::in(['cut', 'complement', 'special_piece']),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'received',
                    'in_progress',
                    'completed',
                    'with_incident',
                    'delayed',
                    'cancelled',
                    'all',
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