<?php

namespace App\Http\Requests;

use App\Models\CourseModality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWhatsappClickRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'course_modality_id' => ['nullable', 'integer', 'exists:course_modalities,id'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['course_id', 'course_modality_id'])) {
                return;
            }

            if ($this->filled('course_id') && $this->filled('course_modality_id') && ! $this->courseModalityBelongsToCourse()) {
                $validator->errors()->add('course_modality_id', 'A modalidade deve pertencer ao curso informado.');
            }
        }];
    }

    private function courseModalityBelongsToCourse(): bool
    {
        return CourseModality::query()
            ->whereKey($this->integer('course_modality_id'))
            ->where('course_id', $this->integer('course_id'))
            ->exists();
    }
}
