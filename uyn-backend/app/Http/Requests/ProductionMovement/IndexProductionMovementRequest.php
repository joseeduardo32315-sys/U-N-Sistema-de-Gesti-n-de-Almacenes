<?php

namespace App\Http\Requests\ProductionMovement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garment_cut_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_cuts', 'id'),
            ],

            'target_type' => [
                'nullable',
                Rule::in([
                    'cut',
                    'complement',
                    'special_piece',
                ]),
            ],

            'process_id' => [
                'nullable',
                'integer',
                Rule::exists('processes', 'id'),
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

            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'received',
                    'in_progress',
                    'partially_completed',
                    'completed',
                    'cancelled',
                    'with_incident',
                    'delayed',
                ]),
            ],

            'search' => ['nullable', 'string', 'max:150'],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}