<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestimonialRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'avatar_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
