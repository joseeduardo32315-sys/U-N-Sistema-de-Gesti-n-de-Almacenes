<?php

namespace App\Http\Requests\GarmentCut;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGarmentCutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        if (array_key_exists('description', $input)) {
            $prepared['description'] = is_string($input['description'])
                && trim($input['description']) !== ''
                    ? trim($input['description'])
                    : null;
        }

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
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'sizes' => [
                'sometimes',
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'sizes.*.size_id' => [
                'bail',
                'required_with:sizes',
                'integer',
                'distinct',
                Rule::exists('sizes', 'id')
                    ->where('status', 'active'),
            ],

            'sizes.*.total_pieces' => [
                'bail',
                'required_with:sizes',
                'integer',
                'min:1',
                'max:1000000',
            ],

            'production_order_id' => ['prohibited'],
            'garment_model_id' => ['prohibited'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
            'current_area_id' => ['prohibited'],
            'total_sizes' => ['prohibited'],
            'base_pieces_per_size' => ['prohibited'],
            'total_pieces' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'sizes.*.size_id.distinct' => 'No puedes repetir una talla dentro del mismo corte.',
            'sizes.*.size_id.exists' => 'Una de las tallas seleccionadas no existe o está inactiva.',
            'sizes.*.total_pieces.min' => 'La cantidad de piezas por talla debe ser mayor a cero.',
        ];
    }
}