<?php

namespace App\Http\Requests\EmbroideryPaymentSetting;

use App\Models\EmbroideryPaymentSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmbroideryPaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        if ($this->has('effective_to') && $this->input('effective_to') === '') {
            $prepared['effective_to'] = null;
        }

        if ($this->has('notes')) {
            $prepared['notes'] = is_string($this->input('notes'))
                && trim((string) $this->input('notes')) !== ''
                    ? trim((string) $this->input('notes'))
                    : null;
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'effective_to' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'operation_process_id' => ['prohibited'],
            'stitch_price' => ['prohibited'],
            'application_price' => ['prohibited'],
            'payment_percentage' => ['prohibited'],
            'minimum_payment_per_piece' => ['prohibited'],
            'default_payment_per_piece' => ['prohibited'],
            'effective_from' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $setting = $this->route('embroidery_payment_setting');

            if (! $setting instanceof EmbroideryPaymentSetting) {
                return;
            }

            $effectiveTo = $this->input(
                'effective_to',
                $setting->effective_to?->toDateString()
            );

            $effectiveFrom = $setting->effective_from?->toDateString();

            if (
                $effectiveTo !== null
                && $effectiveFrom !== null
                && $effectiveTo < $effectiveFrom
            ) {
                $validator->errors()->add(
                    'effective_to',
                    'La fecha final no puede ser anterior a la fecha inicial.'
                );
            }
        });
    }
}