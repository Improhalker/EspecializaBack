<?php

namespace App\Http\Requests\Blog;

use App\Models\BlogPost;
use App\Services\BlogHtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Status and publication date are deliberately absent: they change only through the publication endpoint.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($this->route('post'))],
            'excerpt' => ['required', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:'.BlogHtmlSanitizer::MAX_INPUT_BYTES],
            'category' => ['nullable', 'string', 'max:60'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'cta_action' => ['nullable', Rule::in(BlogPost::CTA_ACTIONS)],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cover_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'cover_alt_text' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens no endereço do artigo.',
            'slug.unique' => 'Já existe um artigo com este endereço. Escolha outro.',
            'cover_media_id.exists' => 'Esta imagem não está disponível. Selecione outra na biblioteca.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título', 'slug' => 'endereço', 'excerpt' => 'resumo', 'body' => 'corpo do artigo',
            'category' => 'categoria', 'course_id' => 'curso relacionado', 'cta_label' => 'texto do botão',
            'meta_title' => 'título para buscadores', 'meta_description' => 'descrição para buscadores',
        ];
    }
}
