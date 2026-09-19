<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'short_name' => $this->short_name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'cover' => $this->resolvedCover(),
            'hero' => $this->when($request->routeIs('courses.show'), fn () => new HeroResource($this->resource)),
            'description' => $this->when($request->routeIs('courses.show'), $this->description),
            'requirements' => $this->when($request->routeIs('courses.show'), $this->requirements ?? []),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category?->id, 'name' => $this->category?->name, 'slug' => $this->category?->slug]),
            'modalities' => CourseModalityResource::collection($this->whenLoaded('modalities')),
            'faqs' => CourseFaqResource::collection($this->whenLoaded('faqs')),
        ];
    }
}
