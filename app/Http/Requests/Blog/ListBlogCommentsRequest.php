<?php

namespace App\Http\Requests\Blog;

use App\Models\BlogComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBlogCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'blog_post_id' => ['nullable', 'integer', 'exists:blog_posts,id'],
            'status' => ['nullable', Rule::in(BlogComment::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
