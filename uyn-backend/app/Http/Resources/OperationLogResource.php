<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'module' => $this->module,
            'action' => $this->action,
            'description' => $this->description,

            'subject' => $this->subject_type
                ? [
                    'type' => class_basename($this->subject_type),
                    'id' => $this->subject_id,
                ]
                : null,

            'old_values' => $this->old_values,
            'new_values' => $this->new_values,

            'ip_address' => $this->ip_address,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'username' => $this->user?->username,
                    'email' => $this->user?->email,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}