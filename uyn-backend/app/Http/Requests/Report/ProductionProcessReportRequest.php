<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionProcessReportRequest extends FormRequest
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
        ];
    }
}