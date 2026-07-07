<?php

namespace App\Http\Requests\EmbroideryPaymentSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmbroideryPaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation_process_id' => [
                'nullable',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive', 'all']),
            ],

            'active_on' => [
                'nullable',
                'date',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}