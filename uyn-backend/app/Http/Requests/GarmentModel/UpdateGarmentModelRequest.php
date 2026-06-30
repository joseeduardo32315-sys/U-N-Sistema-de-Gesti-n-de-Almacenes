<?php

namespace App\Http\Requests\GarmentModel;

use App\Models\GarmentModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGarmentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        if (array_key_exists('code', $input)) {
            $prepared['code'] = Str::upper(
                trim((string) $input['code'])
            );
        }

        if (array_key_exists('name', $input)) {
            $prepared['name'] = trim((string) $input['name']);
        }

        if (array_key_exists('description', $input)) {
            $prepared['description'] = is_string($input['description'])
                && trim($input['description']) !== ''
                    ? trim($input['description'])
                    : null;
        }

        if (array_key_exists('size_range', $input)) {
            $prepared['size_range'] = is_string($input['size_range'])
                && trim($input['size_range']) !== ''
                    ? trim($input['size_range'])
                    : null;
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        /** @var GarmentModel $garmentModel */
        $garmentModel = $this->route('garment_model');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('garment_models', 'code')
                    ->ignore($garmentModel),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'size_range' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'image' => [
                'sometimes',
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código del modelo ya está registrado.',
            'code.regex' => 'El código solo puede contener letras, números, puntos, guiones y guiones bajos.',

            'image.image' => 'El archivo cargado debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe tener formato JPG, JPEG, PNG o WEBP.',
            'image.max' => 'La imagen no puede superar los 5 MB.',
        ];
    }
}