<?php

namespace App\Http\Requests\ProductionMovement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductionMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        if ($this->filled('target_type')) {
            $prepared['target_type'] = Str::lower(
                trim((string) $this->input('target_type'))
            );
        }

        if ($this->has('notes')) {
            $prepared['notes'] = is_string($this->input('notes'))
                && trim((string) $this->input('notes')) !== ''
                    ? trim((string) $this->input('notes'))
                    : null;
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'garment_cut_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('garment_cuts', 'id'),
            ],

            'target_type' => [
                'bail',
                'required',
                Rule::in([
                    'cut',
                    'complement',
                    'special_piece',
                ]),
            ],

            'special_process_piece_id' => [
                'nullable',
                'integer',
                Rule::exists('special_process_pieces', 'id'),
            ],

            'complement_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_cut_complements', 'id'),
            ],

            'process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('processes', 'id'),
            ],

            'operation_process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'quantity' => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:1000000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'from_area_id' => ['prohibited'],
            'to_area_id' => ['prohibited'],
            'status' => ['prohibited'],
            'start_time' => ['prohibited'],
            'end_time' => ['prohibited'],
            'created_by' => ['prohibited'],
            'received_by' => ['prohibited'],
        ];
    }
}