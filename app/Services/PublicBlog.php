<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-side queries shared by the public blog API, the server-rendered HTML and the sitemap.
 */
class PublicBlog
{
    public function findPublished(string $slug): ?BlogPost
    {
        return BlogPost::query()->published()->where('slug', $slug)
            ->with(['coverMedia', 'course.coverMedia', 'course.category'])->first();
    }

    /**
     * Up to $limit published articles: same course first, then same category.
     *
     * @return Collection<int, BlogPost>
     */
    public function related(BlogPost $post, int $limit = 3): Collection
    {
        $related = new Collection;
        $base = fn () => BlogPost::query()->published()->whereKeyNot($post->id)->with('coverMedia')->inListingOrder();

        if ($post->course_id) {
            $related = $base()->where('course_id', $post->course_id)->limit($limit)->get();
        }
        if ($related->count() < $limit && filled($post->category)) {
            $related = $related->merge($base()->where('category', $post->category)
                ->whereNotIn('id', $related->modelKeys())->limit($limit - $related->count())->get());
        }

        return $related->values();
    }

    /** The article that follows this one in the listing order (newest first, id as tie-breaker). */
    public function next(BlogPost $post): ?BlogPost
    {
        return BlogPost::query()->published()
            ->where(fn ($query) => $query->where('published_at', '<', $post->published_at)
                ->orWhere(fn ($query) => $query->where('published_at', $post->published_at)->where('id', '<', $post->id)))
            ->inListingOrder()->first(['id', 'title', 'slug']);
    }

    /** @return Collection<int, Course> */
    public function suggestedCourses(BlogPost $post, int $limit = 3): Collection
    {
        return Course::query()->published()
            ->when($post->course_id, fn ($query, int $courseId) => $query->whereKeyNot($courseId))
            ->with(['category', 'coverMedia'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')
            ->limit($limit)->get();
    }
}
