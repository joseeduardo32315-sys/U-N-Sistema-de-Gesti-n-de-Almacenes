<?php

namespace App\Http\Requests\ProductionIncident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnProductionIncidentForReworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge([
                'notes' => is_string($this->input('notes'))
                    && trim((string) $this->input('notes')) !== ''
                        ? trim((string) $this->input('notes'))
                        : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'operation_process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'notes' => [
                'bail',
                'required',
                'string',
                'min:5',
                'max:3000',
            ],

            'process_id' => ['prohibited'],
            'quantity' => ['prohibited'],
            'target_type' => ['prohibited'],
            'special_process_piece_id' => ['prohibited'],
            'complement_id' => ['prohibited'],
            'from_area_id' => ['prohibited'],
            'to_area_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}