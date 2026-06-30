<?php

namespace App\Http\Requests\GarmentModel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreGarmentModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(
                trim((string) $this->input('code'))
            ),

            'name' => trim((string) $this->input('name')),

            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,

            'size_range' => $this->filled('size_range')
                ? trim((string) $this->input('size_range'))
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'bail',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('garment_models', 'code'),
            ],

            'name' => [
                'bail',
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'size_range' => [
                'nullable',
                'string',
                'max:100',
            ],

            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    public function messages()
    {
        return [
            'code.required' => 'El código del modelo es obligatorio.',
            'code.unique' => 'El código del modelo ya está registrado.',
            'code.regex' => 'El código solo puede contener letras, números, puntos, guiones y guiones bajos.',

            'name.required' => 'El nombre del modelo es obligatorio.',

            'image.image' => 'El archivo cargado debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe tener formato JPG, JPEG, PNG o WEBP.',
            'image.max' => 'La imagen no puede superar los 5 MB.',
        ];
    }
}
