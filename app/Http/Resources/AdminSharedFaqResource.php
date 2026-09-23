<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSharedFaqResource extends JsonResource
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
            'question' => $this->question,
            'answer' => $this->answer,
            'application_mode' => $this->application_mode,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'course_ids' => $this->whenLoaded('courses', fn () => $this->courses->pluck('id')->values()),
            'courses' => $this->whenLoaded('courses', fn () => $this->courses->map(fn ($course) => [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
            ])->values()),
            'courses_count' => $this->when($this->resource->getAttribute('courses_count') !== null, fn (): int => (int) $this->courses_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
