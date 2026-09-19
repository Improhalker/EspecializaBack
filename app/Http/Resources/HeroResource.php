<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HeroResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $desktop = $this->heroMedia;
        $mobile = $this->heroMobileMedia;
        $enabled = (bool) $this->hero_enabled && $desktop?->status === 'ready' && $desktop->visibility === 'public';

        return [
            'enabled' => $enabled,
            'media' => $enabled ? $this->image($desktop) : null,
            'mobile_media' => $enabled && $mobile?->status === 'ready' && $mobile->visibility === 'public' ? $this->image($mobile) : null,
            'position' => $this->hero_image_position ?: 'center',
            'overlay_preset' => $this->hero_overlay_preset ?: 'institutional',
            'overlay_opacity' => (int) ($this->hero_overlay_opacity ?? 75),
        ];
    }

    /** @return array{id: int, url: ?string, width: ?int, height: ?int} */
    private function image(Media $media): array
    {
        return ['id' => $media->id, 'url' => $media->deliveryUrl(), 'width' => $media->width, 'height' => $media->height];
    }
}
