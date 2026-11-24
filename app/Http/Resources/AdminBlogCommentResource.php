<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBlogCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'status' => $this->status,
            'post' => $this->whenLoaded('post', fn () => [
                'id' => $this->post->id, 'title' => $this->post->title, 'slug' => $this->post->slug,
                'is_published' => $this->post->isPublished(),
            ]),
            'moderated_at' => $this->moderated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
