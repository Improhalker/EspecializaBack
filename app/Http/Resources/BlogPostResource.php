<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    /**
     * Public article data. The body is already sanitized when stored.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailed = $request->routeIs('blog.posts.show');
        $course = $this->relationLoaded('course') && $this->course?->is_published ? $this->course : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'category' => $this->category,
            'published_at' => $this->published_at?->toISOString(),
            'reading_minutes' => $this->readingMinutes(),
            'cover' => $this->resolvedCover(),
            'body' => $this->when($detailed, $this->body),
            'meta_title' => $this->when($detailed, $this->meta_title),
            'meta_description' => $this->when($detailed, $this->meta_description),
            'updated_at' => $this->when($detailed, fn () => $this->updated_at?->toISOString()),
            'course' => $this->when($detailed, fn () => $course ? (new CourseResource($course))->resolve($request) : null),
            'cta' => $this->when($detailed, fn () => $course ? [
                'action' => $this->cta_action === 'whatsapp' ? 'whatsapp' : 'course',
                'label' => filled($this->cta_label) ? $this->cta_label : $this->resource->defaultCtaLabel($course),
            ] : null),
        ];
    }
}
