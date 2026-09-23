<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\HeroResource;
use App\Models\Course;
use App\Models\PageAppearance;
use App\Models\SharedFaq;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->published()
            ->with(['category', 'coverMedia'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $appearance = PageAppearance::query()->with(['heroMedia', 'heroMobileMedia'])->where('page_key', 'courses-index')->first()
            ?? new PageAppearance(['page_key' => 'courses-index']);

        return CourseResource::collection($courses)->additional(['meta' => [
            'hero' => (new HeroResource($appearance))->resolve(),
        ]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(string $slug): CourseResource
    {
        $course = Course::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'coverMedia',
                'heroMedia',
                'heroMobileMedia',
                'category',
                'modalities' => fn ($query) => $query->published()->orderBy('sort_order')->orderBy('id'),
                'faqs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->firstOrFail();

        $course->setRelation('sharedFaqs', SharedFaq::query()
            ->published()
            ->applicableTo($course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());

        return new CourseResource($course);
    }
}
