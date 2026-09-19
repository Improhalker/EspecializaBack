<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'uuid' => $this->uuid, 'original_name' => $this->original_name,
            'mime_type' => $this->mime_type, 'original_mime_type' => $this->original_mime_type,
            'extension' => $this->extension, 'original_extension' => $this->original_extension,
            'original_size' => $this->original_size, 'size' => $this->size,
            'width' => $this->width, 'height' => $this->height,
            'original_width' => $this->original_width, 'original_height' => $this->original_height,
            'reduction_percent' => $this->reduction_percent,
            'alt_text' => $this->alt_text, 'alt_is_custom' => $this->alt_is_custom,
            'is_decorative' => $this->is_decorative, 'status' => $this->status,
            'url' => $this->deliveryUrl(),
            'usage_count' => $this->when($this->resource->getAttribute('courses_count') !== null, fn (): int => (int) $this->courses_count + (int) $this->hero_courses_count + (int) $this->mobile_hero_courses_count
                + (int) $this->page_heroes_count + (int) $this->mobile_page_heroes_count),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
