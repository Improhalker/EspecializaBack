<?php

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBlogCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the display name and message are accepted; status is always set by the server.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\pM\pN .\'’-]+$/u'],
            'body' => ['required', 'string', 'min:3', 'max:2000', 'not_regex:/<\s*\/?\s*[a-z!?][^>]*>/i', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (preg_match_all('~https?://|www\.~i', (string) $this->input('body')) > 2) {
                $validator->errors()->add('body', 'Seu comentário tem links demais. Envie no máximo dois links.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'author_name.regex' => 'Use apenas letras, números, espaços e pontuação simples no nome.',
            'body.not_regex' => 'Envie apenas texto simples, sem códigos HTML.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['author_name' => 'nome', 'body' => 'comentário'];
    }
}
