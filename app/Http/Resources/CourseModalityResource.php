<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseModalityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'workload' => $this->workload,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'bonuses' => $this->bonuses ?? [],
            'price_mode' => $this->price_mode,
            'price' => $this->when($this->price_mode === 'visible', $this->price),
            'price_label' => $this->price_label,
            'whatsapp_message' => $this->whatsapp_message,
        ];
    }
}
