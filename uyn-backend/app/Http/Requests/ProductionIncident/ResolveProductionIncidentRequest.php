<?php

namespace App\Http\Requests\ProductionIncident;

use Illuminate\Foundation\Http\FormRequest;

class ResolveProductionIncidentRequest extends FormRequest
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
            'notes' => [
                'bail',
                'required',
                'string',
                'min:5',
                'max:3000',
            ],

            'status' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'resolved_by' => ['prohibited'],
            'quantity_affected' => ['prohibited'],
            'responsible_employee_id' => ['prohibited'],
        ];
    }
}