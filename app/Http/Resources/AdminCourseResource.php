<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCourseResource extends JsonResource
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
            'description' => $this->description,
            'cover_image_path' => $this->cover_image_path,
            'cover_media_id' => $this->cover_media_id,
            'cover_alt_text' => $this->cover_alt_text,
            'cover' => $this->resolvedCover(),
            'cover_media' => new MediaResource($this->whenLoaded('coverMedia')),
            'hero_enabled' => (bool) $this->hero_enabled,
            'hero_media_id' => $this->hero_media_id,
            'hero_mobile_media_id' => $this->hero_mobile_media_id,
            'hero_image_position' => $this->hero_image_position,
            'hero_overlay_preset' => $this->hero_overlay_preset,
            'hero_overlay_opacity' => (int) ($this->hero_overlay_opacity ?? 75),
            'hero_media' => new MediaResource($this->whenLoaded('heroMedia')),
            'hero_mobile_media' => new MediaResource($this->whenLoaded('heroMobileMedia')),
            'requirements' => $this->requirements ?? [],
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'is_featured' => $this->is_featured,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'modalities' => $this->whenLoaded('modalities', fn () => $this->modalities->map(fn ($modality) => [
                'id' => $modality->id,
                'name' => $modality->name,
                'workload' => $modality->workload,
                'description' => $modality->description,
                'features' => $modality->features ?? [],
                'bonuses' => $modality->bonuses ?? [],
                'price_mode' => $modality->price_mode,
                'price' => $modality->price,
                'price_label' => $modality->price_label,
                'whatsapp_message' => $modality->whatsapp_message,
                'sort_order' => $modality->sort_order,
                'is_published' => $modality->is_published,
            ])->values()),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
