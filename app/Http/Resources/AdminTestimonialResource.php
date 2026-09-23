<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTestimonialResource extends JsonResource
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
            'content' => $this->content,
            'rating' => $this->rating,
            'avatar_media_id' => $this->avatar_media_id,
            'avatar' => $this->resolvedAvatar(),
            'avatar_media' => new MediaResource($this->whenLoaded('avatarMedia')),
            'course_id' => $this->course_id,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
