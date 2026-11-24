<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogCommentRequest;
use App\Http\Resources\BlogCommentResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BlogCommentController extends Controller
{
    public const PENDING_MESSAGE = 'Seu comentário foi enviado e aguarda aprovação.';

    public function index(string $slug): AnonymousResourceCollection
    {
        $post = BlogPost::query()->published()->where('slug', $slug)->firstOrFail();

        return BlogCommentResource::collection(
            $post->comments()->approved()->orderBy('created_at')->orderBy('id')->paginate(20),
        );
    }

    public function store(StoreBlogCommentRequest $request, string $slug): JsonResponse
    {
        $post = BlogPost::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        // Honeypot: automated submissions get the same answer but nothing is stored.
        if (filled($data['website'] ?? null)) {
            return response()->json(['message' => self::PENDING_MESSAGE], 201);
        }

        $duplicate = $post->comments()->where('body', $data['body'])->where('created_at', '>=', now()->subDay())->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['body' => 'Este comentário já foi enviado e aguarda aprovação.']);
        }

        $post->comments()->create(['author_name' => $data['author_name'], 'body' => $data['body']]);

        return response()->json(['message' => self::PENDING_MESSAGE], 201);
    }
}
