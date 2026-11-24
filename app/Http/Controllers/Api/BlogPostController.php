<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Http\Resources\CourseResource;
use App\Models\BlogPost;
use App\Services\PublicBlog;
use App\Services\PublicCourseCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BlogPostController extends Controller
{
    public function __construct(private PublicCourseCache $cache, private PublicBlog $blog) {}

    public function index(Request $request): Response
    {
        $page = max(1, min(1000, (int) $request->query('page', 1)));
        $perPage = max(1, min(24, (int) $request->query('per_page', 9)));
        $key = $this->cache->key('__blog-index__:'.$page.':'.$perPage);
        $json = $this->cache->get($key);

        if ($json === null) {
            $posts = BlogPost::query()->published()->with('coverMedia')->inListingOrder()
                ->paginate($perPage, ['*'], 'page', $page);
            $json = BlogPostResource::collection($posts)->response()->getContent();
            $this->cache->put($key, $json);
        }

        return $this->json($json);
    }

    public function show(Request $request, string $slug): Response
    {
        $key = $this->cache->key('__blog__:'.$slug);
        $json = $this->cache->get($key);

        if ($json === null) {
            $post = $this->blog->findPublished($slug);
            abort_unless($post, 404, 'Artigo não encontrado.');
            $next = $this->blog->next($post);

            $json = (new BlogPostResource($post))->additional(['meta' => [
                'related' => BlogPostResource::collection($this->blog->related($post))->resolve($request),
                'next' => $next ? ['title' => $next->title, 'slug' => $next->slug] : null,
                'suggested_courses' => CourseResource::collection($this->blog->suggestedCourses($post))->resolve($request),
            ]])->response()->getContent();
            $this->cache->put($key, $json);
        }

        return $this->json($json);
    }

    private function json(string $json): Response
    {
        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.config('site.public_response_cache_seconds'),
        ]);
    }
}
