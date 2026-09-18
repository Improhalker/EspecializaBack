<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'alt_text' => [Rule::requiredIf(! $this->boolean('is_decorative')), 'nullable', 'string', 'max:500'],
            'is_decorative' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['alt_text.required' => 'Informe o texto alternativo para uma imagem de conteúdo público.',
            'alt_text.max' => 'O texto alternativo deve ter no máximo 500 caracteres.'];
    }
}
