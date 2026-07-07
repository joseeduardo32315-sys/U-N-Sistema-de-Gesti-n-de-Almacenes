<?php

namespace App\Http\Requests\EmbroideryPaymentSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmbroideryPaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge([
                'notes' => is_string($this->input('notes'))
                    && trim((string) $this->input('notes')) !== ''
                        ? trim((string) $this->input('notes'))
                        : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'operation_process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('operation_processes', 'id'),
            ],

            'stitch_price' => [
                'bail',
                'required',
                'numeric',
                'min:0.00000001',
                'max:999999.99999999',
            ],

            'application_price' => [
                'bail',
                'required',
                'numeric',
                'min:0',
                'max:99999999.9999',
            ],

            'payment_percentage' => [
                'bail',
                'required',
                'numeric',
                'min:0.000001',
                'max:1',
            ],

            'minimum_payment_per_piece' => [
                'bail',
                'required',
                'numeric',
                'min:0.0001',
                'max:99999999.9999',
            ],

            'default_payment_per_piece' => [
                'bail',
                'required',
                'numeric',
                'min:0.0001',
                'max:99999999.9999',
            ],

            'effective_from' => [
                'bail',
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimum = (float) $this->input(
                'minimum_payment_per_piece',
                0
            );

            $default = (float) $this->input(
                'default_payment_per_piece',
                0
            );

            if ($default < $minimum) {
                $validator->errors()->add(
                    'default_payment_per_piece',
                    'El pago predeterminado por pieza debe ser igual o mayor al pago mínimo por pieza.'
                );
            }
        });
    }
}