<?php

namespace App\Http\Requests\GarmentCut;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreGarmentCutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(
                trim((string) $this->input('code'))
            ),

            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,

            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'production_order_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('production_orders', 'id'),
            ],

            'garment_model_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('garment_models', 'id')
                    ->where('status', 'active'),
            ],

            'code' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('garment_cuts', 'code'),
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'sizes' => [
                'bail',
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'sizes.*.size_id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('sizes', 'id')
                    ->where('status', 'active'),
            ],

            'sizes.*.total_pieces' => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:1000000',
            ],

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
            'production_order_id.required' => 'Debes seleccionar una orden de producción.',
            'production_order_id.exists' => 'La orden de producción seleccionada no existe.',

            'garment_model_id.required' => 'Debes seleccionar un modelo de prenda activo.',
            'garment_model_id.exists' => 'El modelo de prenda seleccionado no existe o está inactivo.',

            'code.required' => 'El folio del corte es obligatorio.',
            'code.unique' => 'El folio del corte ya está registrado.',
            'code.regex' => 'El folio solo puede contener letras, números, puntos, guiones y guiones bajos.',

            'sizes.required' => 'Debes registrar al menos una talla.',
            'sizes.min' => 'Debes registrar al menos una talla.',

            'sizes.*.size_id.distinct' => 'No puedes repetir una talla dentro del mismo corte.',
            'sizes.*.size_id.exists' => 'Una de las tallas seleccionadas no existe o está inactiva.',

            'sizes.*.total_pieces.required' => 'Debes indicar la cantidad de piezas por talla.',
            'sizes.*.total_pieces.min' => 'La cantidad de piezas por talla debe ser mayor a cero.',
        ];
    }
}