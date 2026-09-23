<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\HeroResource;
use App\Models\Course;
use App\Models\PageAppearance;
use App\Models\SharedFaq;
use App\Services\PublicCourseCache;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
    public function show(string $slug, PublicCourseCache $cache): Response
    {
        $started = hrtime(true);
        $databaseMs = 0.0;
        $serializationMs = 0.0;
        $key = $cache->key($slug);
        $json = $cache->get($key);
        $hit = $json !== null;

        if (! $hit) {
            $databaseStarted = hrtime(true);
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
            $databaseMs = (hrtime(true) - $databaseStarted) / 1_000_000;

            $serializationStarted = hrtime(true);
            $json = (new CourseResource($course))->response()->getContent();
            $serializationMs = (hrtime(true) - $serializationStarted) / 1_000_000;
            $cache->put($key, $json);
        }

        $totalMs = (hrtime(true) - $started) / 1_000_000;

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Timing-Allow-Origin' => '*',
            'Server-Timing' => sprintf('course-cache;desc="%s", db;dur=%.1f, serialize;dur=%.1f, app;dur=%.1f',
                $hit ? 'hit' : 'miss', $databaseMs, $serializationMs, $totalMs),
        ]);
    }
}
