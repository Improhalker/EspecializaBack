<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
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

        return CourseResource::collection($courses);
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
                'category',
                'modalities' => fn ($query) => $query->published()->orderBy('sort_order')->orderBy('id'),
                'faqs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->firstOrFail();

        return new CourseResource($course);
    }
}
