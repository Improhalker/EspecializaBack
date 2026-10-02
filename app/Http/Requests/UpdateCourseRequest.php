<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'short_name' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('courses', 'slug')->ignore($this->route('course'))],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cover_alt_text' => ['nullable', 'string', 'max:500'],
            'cover_image_path' => ['nullable', 'string', 'max:2048'],
            'hero_enabled' => ['sometimes', 'boolean'],
            'hero_media_id' => ['required_if:hero_enabled,true', 'nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'hero_mobile_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'hero_image_position' => ['sometimes', 'string', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
            'hero_overlay_preset' => ['sometimes', 'string', Rule::in(['institutional', 'dark', 'soft'])],
            'hero_overlay_opacity' => ['sometimes', 'integer', 'between:0,100'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['required', 'string', 'max:500'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'modalities' => ['nullable', 'array', 'max:2'],
            'modalities.*.id' => ['nullable', 'integer'],
            'modalities.*.name' => ['required', 'string', Rule::in(['Formação', 'Atualização'])],
            'modalities.*.workload' => ['nullable', 'string', 'max:80'],
            'modalities.*.characteristics' => ['nullable', 'array:format,materials,assessment,certificate,support,access,video_lessons_count,video_lessons_duration'],
            'modalities.*.characteristics.format' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.materials' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.assessment' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.certificate' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.support' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.access' => ['nullable', 'string', 'max:300'],
            'modalities.*.characteristics.video_lessons_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'modalities.*.characteristics.video_lessons_duration' => ['nullable', 'string', 'max:150'],
            'modalities.*.description' => ['nullable', 'string'],
            'modalities.*.features' => ['nullable', 'array'],
            'modalities.*.features.*' => ['required', 'string', 'max:500'],
            'modalities.*.bonuses' => ['nullable', 'array'],
            'modalities.*.bonuses.*' => ['required', 'string', 'max:500'],
            'modalities.*.price_mode' => ['required', 'string', Rule::in(['hidden', 'consult', 'from', 'visible'])],
            'modalities.*.price' => ['nullable', 'numeric', 'min:0'],
            'modalities.*.price_label' => ['nullable', 'string', 'max:255'],
            'modalities.*.whatsapp_message' => ['nullable', 'string', 'max:1000'],
            'modalities.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'modalities.*.is_published' => ['sometimes', 'boolean'],
            'faqs' => ['nullable', 'array'],
            'faqs.*.id' => ['nullable', 'integer'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string'],
            'faqs.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'modalities.*.characteristics.array' => 'As características devem conter apenas os campos disponíveis no formulário.',
            'modalities.*.characteristics.*.string' => 'Preencha esta característica com um texto.',
            'modalities.*.characteristics.*.max' => 'O texto desta característica ultrapassa o limite permitido.',
            'modalities.*.characteristics.video_lessons_count.integer' => 'A quantidade de videoaulas deve ser um número inteiro.',
            'modalities.*.characteristics.video_lessons_count.min' => 'Informe ao menos uma videoaula ou deixe o campo vazio.',
            'modalities.*.characteristics.video_lessons_count.max' => 'A quantidade de videoaulas ultrapassa o limite permitido.',
        ];
    }
}
