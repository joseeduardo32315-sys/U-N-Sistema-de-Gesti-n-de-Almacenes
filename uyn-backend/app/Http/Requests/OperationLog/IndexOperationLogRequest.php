<?php

namespace App\Http\Requests\OperationLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOperationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operation-logs.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:80'],

            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}