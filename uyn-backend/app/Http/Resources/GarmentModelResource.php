<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GarmentModelResource extends JsonResource
{
    
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'size_range' => $this->size_range,

            'image_path' => $this->image_path,

            'image_url' => $this->image_path
                ? url(Storage::disk('public')->url($this->image_path))
                : null,

            'status' => $this->status,

            'status_label' => $this->status === 'active'
                ? 'Activo'
                : 'Inactivo',

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}