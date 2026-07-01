<?php

namespace App\Http\Requests\PieceType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexPieceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status')) {
            $this->merge([
                'status' => Str::lower(
                    trim((string) $this->input('status'))
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive', 'all']),
            ],
        ];
    }
}