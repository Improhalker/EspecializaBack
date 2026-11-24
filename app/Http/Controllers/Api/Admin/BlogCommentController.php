<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\ListBlogCommentsRequest;
use App\Http\Requests\Blog\UpdateBlogCommentStatusRequest;
use App\Http\Resources\AdminBlogCommentResource;
use App\Models\BlogComment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogCommentController extends Controller
{
    public function index(ListBlogCommentsRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $scope = fn () => BlogComment::query()
            ->when($data['blog_post_id'] ?? null, fn ($query, int $postId) => $query->where('blog_post_id', $postId));

        $comments = $scope()->with('post')
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20)->withQueryString();

        $counts = $scope()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return AdminBlogCommentResource::collection($comments)->additional(['counts' => collect(BlogComment::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])]);
    }

    public function update(UpdateBlogCommentStatusRequest $request, BlogComment $comment): AdminBlogCommentResource
    {
        $comment->forceFill(['status' => $request->validated('status'), 'moderated_at' => now()])->save();

        return new AdminBlogCommentResource($comment->load('post'));
    }
}
