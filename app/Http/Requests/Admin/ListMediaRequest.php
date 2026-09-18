<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'sort' => ['sometimes', Rule::in(['created_at', 'original_name', 'size'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return ['sort.in' => 'Ordenação inválida.', 'direction.in' => 'Direção de ordenação inválida.',
            'per_page.max' => 'Solicite no máximo 60 imagens por página.'];
    }
}
