<?php

namespace App\Http\Requests\Blog;

use App\Services\BlogHtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;

class PreviewBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['body' => ['present', 'nullable', 'string', 'max:'.BlogHtmlSanitizer::MAX_INPUT_BYTES]];
    }
}
