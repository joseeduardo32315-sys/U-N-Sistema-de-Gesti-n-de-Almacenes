<?php

namespace App\Http\Requests\GarmentCut;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureGarmentCutClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $prepared = [];

        if (array_key_exists('complement_notes', $input)) {
            $prepared['complement_notes'] = is_string(
                $input['complement_notes']
            ) && trim($input['complement_notes']) !== ''
                ? trim($input['complement_notes'])
                : null;
        }

        if (
            array_key_exists(
                'special_process_pieces',
                $input
            )
            && is_array($input['special_process_pieces'])
        ) {
            $prepared['special_process_pieces'] = collect(
                $input['special_process_pieces']
            )
                ->map(function ($piece) {
                    return [
                        'piece_type_id' => $piece['piece_type_id'] ?? null,
                        'process_id' => $piece['process_id'] ?? null,

                        'notes' => isset($piece['notes'])
                            && is_string($piece['notes'])
                            && trim($piece['notes']) !== ''
                                ? trim($piece['notes'])
                                : null,
                    ];
                })
                ->values()
                ->all();
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'complement_notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            /*
             * Debe enviarse incluso cuando no existan piezas
             * especiales: "special_process_pieces": []
             */
            'special_process_pieces' => [
                'present',
                'array',
                'max:20',
            ],

            'special_process_pieces.*.piece_type_id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('piece_types', 'id')
                    ->where('status', 'active'),
            ],

            'special_process_pieces.*.process_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('processes', 'id'),
            ],

            'special_process_pieces.*.notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'status' => ['prohibited'],
            'current_area_id' => ['prohibited'],
            'total_pieces' => ['prohibited'],

            'special_process_pieces.*.status' => [
                'prohibited',
            ],

            'special_process_pieces.*.current_area_id' => [
                'prohibited',
            ],

            'special_process_pieces.*.total_pieces' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'special_process_pieces.present' =>
                'Debes enviar el arreglo de piezas con proceso especial, aunque esté vacío.',

            'special_process_pieces.*.piece_type_id.distinct' =>
                'No puedes registrar dos rutas especiales para el mismo tipo de pieza.',

            'special_process_pieces.*.piece_type_id.exists' =>
                'Uno de los tipos de pieza no existe o está inactivo.',

            'special_process_pieces.*.process_id.exists' =>
                'Uno de los procesos seleccionados no existe.',

            'status.prohibited' =>
                'El estado se controla mediante los movimientos de producción.',

            'current_area_id.prohibited' =>
                'El área actual se controla mediante los movimientos de producción.',
        ];
    }
}