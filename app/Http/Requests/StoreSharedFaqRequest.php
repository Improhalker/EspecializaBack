<?php

namespace App\Http\Requests;

use App\Models\SharedFaq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharedFaqRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'application_mode' => ['required', Rule::in([SharedFaq::APPLICATION_ALL_COURSES, SharedFaq::APPLICATION_SELECTED_COURSES])],
            'course_ids' => ['required_if:application_mode,'.SharedFaq::APPLICATION_SELECTED_COURSES, 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
