<?php

namespace App\Http\Requests\ProductionOrder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],

            'status' => [
                'nullable',
                Rule::in([
                    'registered',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],

            'priority' => [
                'nullable',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],

            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
