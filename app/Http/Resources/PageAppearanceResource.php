<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageAppearanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = config('page_appearances.pages.'.$this->page_key, []);

        return [
            'page_key' => $this->page_key,
            'label' => $definition['label'] ?? $this->page_key,
            'path' => $definition['path'] ?? null,
            'sort_order' => $definition['sort_order'] ?? 0,
            'hero_enabled' => (bool) $this->hero_enabled,
            'hero_media_id' => $this->hero_media_id,
            'hero_mobile_media_id' => $this->hero_mobile_media_id,
            'hero_image_position' => $this->hero_image_position,
            'hero_overlay_preset' => $this->hero_overlay_preset,
            'hero_overlay_opacity' => (int) $this->hero_overlay_opacity,
            'hero_media' => new MediaResource($this->whenLoaded('heroMedia')),
            'hero_mobile_media' => new MediaResource($this->whenLoaded('heroMobileMedia')),
            'hero' => new HeroResource($this->resource),
        ];
    }
}
