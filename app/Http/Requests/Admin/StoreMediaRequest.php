<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:'.config('media.max_upload_kb'),
                'extensions:jpg,jpeg,png,webp,svg', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'course_name' => ['nullable', 'string', 'max:255'],
            'is_decorative' => ['sometimes', 'boolean'],
            'upload_key' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione uma imagem para enviar.',
            'file.file' => 'O envio deve conter um arquivo de imagem válido.',
            'file.max' => 'A imagem excede o limite de '.config('media.max_upload_kb').' KB.',
            'file.extensions' => 'Envie uma imagem JPG, PNG, WebP ou SVG.',
            'file.mimetypes' => 'O conteúdo do arquivo não é uma imagem JPG, PNG, WebP ou SVG válida.',
            'alt_text.max' => 'O texto alternativo deve ter no máximo 500 caracteres.',
        ];
    }
}
