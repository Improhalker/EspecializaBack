<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\ListBlogPostsRequest;
use App\Http\Requests\Blog\PreviewBlogPostRequest;
use App\Http\Requests\Blog\StoreBlogPostRequest;
use App\Http\Requests\Blog\UpdateBlogPostPublicationRequest;
use App\Http\Requests\Blog\UpdateBlogPostRequest;
use App\Http\Resources\AdminBlogPostResource;
use App\Models\BlogPost;
use App\Models\Media;
use App\Services\BlogHtmlSanitizer;
use App\Services\PublicCourseCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogPostController extends Controller
{
    public function __construct(private PublicCourseCache $publicCache, private BlogHtmlSanitizer $sanitizer) {}

    public function index(ListBlogPostsRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $direction = $data['direction'] ?? 'desc';

        $posts = BlogPost::query()
            ->with(['course', 'coverMedia'])
            ->withCount(['comments as pending_comments_count' => fn ($query) => $query->where('status', 'pending')])
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->whereLike('title', '%'.$search.'%')->orWhereLike('slug', '%'.$search.'%')))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($data['sort'] ?? 'updated_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($data['per_page'] ?? 15)->withQueryString();

        return AdminBlogPostResource::collection($posts);
    }

    public function store(StoreBlogPostRequest $request): AdminBlogPostResource
    {
        $post = DB::transaction(function () use ($request): BlogPost {
            $post = new BlogPost(['status' => BlogPost::STATUS_DRAFT]);

            return $this->save($post, $request->validated());
        });

        return new AdminBlogPostResource($post->load(['course', 'coverMedia']));
    }

    public function show(BlogPost $post): AdminBlogPostResource
    {
        return new AdminBlogPostResource($post->load(['course', 'coverMedia']));
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): AdminBlogPostResource
    {
        $post = DB::transaction(fn (): BlogPost => $this->save($post, $request->validated()));
        $this->publicCache->invalidate();

        return new AdminBlogPostResource($post->fresh()->load(['course', 'coverMedia']));
    }

    public function updatePublication(UpdateBlogPostPublicationRequest $request, BlogPost $post): AdminBlogPostResource
    {
        $status = $request->validated('status');
        if ($status === BlogPost::STATUS_PUBLISHED && ! BlogHtmlSanitizer::hasContent((string) $post->body)) {
            throw ValidationException::withMessages(['body' => 'Adicione conteúdo ao corpo do artigo antes de publicar.']);
        }

        $post->update([
            'status' => $status,
            'published_at' => $status === BlogPost::STATUS_PUBLISHED ? ($post->published_at ?? now()) : $post->published_at,
        ]);
        $this->publicCache->invalidate();

        return new AdminBlogPostResource($post->load(['course', 'coverMedia']));
    }

    public function destroy(BlogPost $post): JsonResponse
    {
        $post->delete();
        $this->publicCache->invalidate();

        return response()->json([], 204);
    }

    /** Sanitizes exactly like a save, without persisting anything. */
    public function preview(PreviewBlogPostRequest $request): JsonResponse
    {
        $result = $this->sanitizer->sanitize((string) $request->validated('body'));

        return response()->json(['data' => ['html' => $result['html'], 'removed' => $result['removed']]]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => BlogPost::query()->whereNotNull('category')
            ->distinct()->orderBy('category')->limit(100)->pluck('category')]);
    }

    /** @param array<string, mixed> $data */
    private function save(BlogPost $post, array $data): BlogPost
    {
        $sanitized = $this->sanitizer->sanitize((string) $data['body']);
        if (! BlogHtmlSanitizer::hasContent($sanitized['html'])) {
            throw ValidationException::withMessages(['body' => 'O corpo do artigo ficou vazio após a revisão de segurança. Use texto e formatação simples.']);
        }

        if (isset($data['cover_media_id'])) {
            $media = Media::query()->lockForUpdate()->find($data['cover_media_id']);
            if (! $media || $media->status !== 'ready' || $media->visibility !== 'public') {
                throw ValidationException::withMessages(['cover_media_id' => 'Esta imagem não está disponível. Selecione outra na biblioteca.']);
            }
        }
        if ($sanitized['media_ids'] !== []) {
            Media::query()->whereKey($sanitized['media_ids'])->lockForUpdate()->get();
        }

        $post->fill([
            'title' => trim($data['title']),
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['title'], $post->id),
            'excerpt' => trim($data['excerpt']),
            'body' => $sanitized['html'],
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'course_id' => $data['course_id'] ?? null,
            'cta_action' => $data['cta_action'] ?? 'course',
            'cta_label' => filled($data['cta_label'] ?? null) ? trim($data['cta_label']) : null,
            'cover_media_id' => $data['cover_media_id'] ?? null,
            'cover_alt_text' => isset($data['cover_media_id']) && filled($data['cover_alt_text'] ?? null) ? trim($data['cover_alt_text']) : null,
            'meta_title' => filled($data['meta_title'] ?? null) ? trim($data['meta_title']) : null,
            'meta_description' => filled($data['meta_description'] ?? null) ? trim($data['meta_description']) : null,
        ])->save();
        $post->bodyMedia()->sync($sanitized['media_ids']);

        return $post;
    }

    private function uniqueSlug(string $title, ?int $ignoreId): string
    {
        $base = Str::limit(Str::slug($title), 150, '') ?: 'artigo';
        $slug = $base;
        for ($suffix = 2; BlogPost::query()->where('slug', $slug)->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
