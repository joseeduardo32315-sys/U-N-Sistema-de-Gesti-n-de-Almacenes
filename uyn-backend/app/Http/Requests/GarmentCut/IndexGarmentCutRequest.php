<?php

namespace App\Http\Requests\GarmentCut;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGarmentCutRequest extends FormRequest
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

            'production_order_id' => [
                'nullable',
                'integer',
                Rule::exists('production_orders', 'id'),
            ],

            'garment_model_id' => [
                'nullable',
                'integer',
                Rule::exists('garment_models', 'id'),
            ],

            'current_area_id' => [
                'nullable',
                'integer',
                Rule::exists('areas', 'id'),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'registered',
                    'in_progress',
                    'partially_completed',
                    'completed',
                    'cancelled',
                    'with_incident',
                    'delayed',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'nax:100',
            ],
        ];
    }
}
