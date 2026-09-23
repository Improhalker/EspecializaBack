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
            'faqs' => $this->when(
                $this->relationLoaded('faqs') || $this->relationLoaded('sharedFaqs'),
                fn () => $this->composedFaqs(),
            ),
        ];
    }

    /**
     * Compose the public FAQ list: applicable shared FAQs first (ordered by sort_order),
     * then the course's own individual FAQs. A shared FAQ is dropped when an individual
     * FAQ with the same normalized question already exists, so the individual one wins.
     *
     * @return array<int, array<string, mixed>>
     */
    private function composedFaqs(): array
    {
        $individual = $this->relationLoaded('faqs') ? $this->faqs : collect();
        $shared = $this->relationLoaded('sharedFaqs') ? $this->sharedFaqs : collect();

        $individualQuestions = $individual->map(fn ($faq) => self::normalizeQuestion($faq->question));

        $sharedItems = $shared
            ->reject(fn ($faq) => $individualQuestions->contains(self::normalizeQuestion($faq->question)))
            ->map(fn ($faq) => [
                'id' => "shared-{$faq->id}",
                'question' => $faq->question,
                'answer' => $faq->answer,
                'source' => 'shared',
                'sort_order' => $faq->sort_order,
            ]);

        $individualItems = $individual->map(fn ($faq) => [
            'id' => "course-{$faq->id}",
            'question' => $faq->question,
            'answer' => $faq->answer,
            'source' => 'course',
            'sort_order' => $faq->sort_order,
        ]);

        return $sharedItems->concat($individualItems)->values()->all();
    }

    private static function normalizeQuestion(string $question): string
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return rtrim($normalized, '.!?');
    }
}
