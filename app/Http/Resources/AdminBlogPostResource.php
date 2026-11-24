<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBlogPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'category' => $this->category,
            'course_id' => $this->course_id,
            'course' => $this->whenLoaded('course', fn () => $this->course ? [
                'id' => $this->course->id, 'name' => $this->course->name, 'slug' => $this->course->slug,
                'is_published' => $this->course->is_published,
            ] : null),
            'cta_action' => $this->cta_action,
            'cta_label' => $this->cta_label,
            'cover_media_id' => $this->cover_media_id,
            'cover_media' => new MediaResource($this->whenLoaded('coverMedia')),
            'cover_alt_text' => $this->cover_alt_text,
            'cover' => $this->resolvedCover(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toISOString(),
            'reading_minutes' => $this->readingMinutes(),
            'pending_comments_count' => $this->when($this->resource->getAttribute('pending_comments_count') !== null, fn (): int => (int) $this->pending_comments_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
