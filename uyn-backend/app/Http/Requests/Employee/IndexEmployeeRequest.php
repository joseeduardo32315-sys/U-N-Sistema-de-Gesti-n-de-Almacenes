<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeRequest extends FormRequest
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

            'area_id' => [
                'nullable', 
                'integer', 
                Rule::exists('areas', 'id')
            ],

            'worker_type' => [
                'nullable', 
                'string', 
                Rule::in(['internal', 'external'])
            ],

            'status' => [
                'nullable', 
                Rule::in(['active', 'inactive'])
            ],

            'per_page' => [
                'nullable', 
                'integer', 
                'min:1', 
                'max:100'
            ],
        ];
    }
}
