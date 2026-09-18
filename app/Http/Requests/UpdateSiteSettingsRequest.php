<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
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
            'settings' => ['sometimes', 'array'],
            'settings.contact' => ['sometimes', 'array'],
            'settings.contact.whatsapp_number' => ['nullable', 'string', 'max:30'],
            'settings.contact.default_message' => ['nullable', 'string', 'max:1000'],
            'settings.social_links' => ['sometimes', 'array'],
            'settings.institutional' => ['sometimes', 'array'],
            'settings.global_faq' => ['sometimes', 'array'],
            'settings.global_faq.*.question' => ['required', 'string', 'max:255'],
            'settings.global_faq.*.answer' => ['required', 'string'],
            'featured_course_ids' => ['sometimes', 'array'],
            'featured_course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
        ];
    }
}
