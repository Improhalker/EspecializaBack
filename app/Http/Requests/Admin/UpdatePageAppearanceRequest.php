<?php

namespace App\Http\Requests\Admin;

use App\Models\PageAppearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePageAppearanceRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hero_enabled' => ['sometimes', 'boolean'],
            'hero_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'hero_mobile_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('status', 'ready')->where('visibility', 'public')],
            'hero_image_position' => ['sometimes', 'string', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
            'hero_overlay_preset' => ['sometimes', 'string', Rule::in(['institutional', 'dark', 'soft'])],
            'hero_overlay_opacity' => ['sometimes', 'integer', 'between:0,100'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existing = PageAppearance::query()->where('page_key', $this->route('pageKey'))->first();
            $enabled = $this->has('hero_enabled') ? $this->boolean('hero_enabled') : (bool) $existing?->hero_enabled;
            $mediaId = $this->has('hero_media_id') ? $this->input('hero_media_id') : $existing?->hero_media_id;

            if ($enabled && ! $mediaId) {
                $validator->errors()->add('hero_media_id', 'Selecione uma imagem de fundo para ativar o banner.');
            }
        }];
    }
}
