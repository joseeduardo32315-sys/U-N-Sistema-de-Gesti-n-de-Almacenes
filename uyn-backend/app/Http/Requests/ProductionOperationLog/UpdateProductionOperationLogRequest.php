<?php

namespace App\Http\Requests\ProductionOperationLog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionOperationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        if (array_key_exists('notes', $input)) {
            $prepared['notes'] = is_string($input['notes'])
                && trim($input['notes']) !== ''
                    ? trim($input['notes'])
                    : null;
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'start' => [
                'sometimes',
                'boolean',
            ],

            'complete' => [
                'sometimes',
                'boolean',
            ],

            'quantity_processed' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'stitches_count' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:100000000',
            ],

            'applications_count' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:100000000',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'employee_id' => ['prohibited'],
            'production_movement_id' => ['prohibited'],
            'operation_process_id' => ['prohibited'],
            'status' => ['prohibited'],
            'start_time' => ['prohibited'],
            'end_time' => ['prohibited'],
            'payout_amount' => ['prohibited'],
        ];
    }
}