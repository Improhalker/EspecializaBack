<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSharedFaqsRequest extends FormRequest
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
            'status' => ['nullable', 'in:published,draft'],
            'application_mode' => ['nullable', 'in:all_courses,selected_courses'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'sort' => ['nullable', 'in:question,sort_order,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
