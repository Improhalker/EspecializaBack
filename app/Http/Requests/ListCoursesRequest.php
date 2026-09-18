<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCoursesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:published,draft'],
            'featured' => ['nullable', 'boolean'],
            'modality' => ['nullable', 'in:Formação,Atualização'],
            'sort' => ['nullable', 'in:name,updated_at,sort_order'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
