<?php

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlogCommentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['status' => ['required', 'in:approved,rejected,hidden']];
    }
}
