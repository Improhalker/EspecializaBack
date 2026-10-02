<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'uuid'],
            'page' => ['required', 'array:id,path,active_ms,scroll_depth,lcp_ms,inp_ms,cls,referrer_host,utm_source,utm_medium,utm_campaign'],
            'page.id' => ['required', 'uuid'],
            'page.path' => ['required', 'string', 'max:512', 'regex:~^/(?:cursos(?:/[a-z0-9-]+)?|quem-somos|politica-de-privacidade|termos-de-uso)?$~'],
            'page.active_ms' => ['required', 'integer', 'min:0', 'max:14400000'],
            'page.scroll_depth' => ['required', 'integer', 'min:0', 'max:100'],
            'page.lcp_ms' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'page.inp_ms' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'page.cls' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'page.referrer_host' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/i'],
            'page.utm_source' => ['nullable', 'string', 'max:80', 'regex:/^[\\pL\\pN _.-]+$/u'],
            'page.utm_medium' => ['nullable', 'string', 'max:80', 'regex:/^[\\pL\\pN _.-]+$/u'],
            'page.utm_campaign' => ['nullable', 'string', 'max:80', 'regex:/^[\\pL\\pN _.-]+$/u'],
            'interactions' => ['present', 'array', 'max:25'],
            'interactions.*' => ['array:id,kind,label'],
            'interactions.*.id' => ['required', 'uuid', 'distinct'],
            'interactions.*.kind' => ['required', Rule::in(['whatsapp', 'navigation', 'button', 'faq', 'filter'])],
            'interactions.*.label' => ['required', 'string', 'max:120', 'not_regex:/[<>@]/'],
        ];
    }

    public function messages(): array
    {
        return ['page.path.regex' => 'Apenas páginas públicas podem ser contabilizadas.', 'interactions.max' => 'Envie no máximo 25 interações por vez.'];
    }
}
