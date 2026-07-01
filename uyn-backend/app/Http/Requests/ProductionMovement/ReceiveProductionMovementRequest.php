<?php

namespace App\Http\Requests\ProductionMovement;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveProductionMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['prohibited'],
            'received_by' => ['prohibited'],
            'start_time' => ['prohibited'],
            'end_time' => ['prohibited'],
            'quantity' => ['prohibited'],
            'from_area_id' => ['prohibited'],
            'to_area_id' => ['prohibited'],
        ];
    }
}