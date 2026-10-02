<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'path' => ['nullable', 'string', 'max:512', 'regex:~^/(?:cursos(?:/[a-z0-9-]+)?|quem-somos|politica-de-privacidade|termos-de-uso)?$~'],
            'device' => ['nullable', Rule::in(['desktop', 'mobile', 'tablet'])],
            'utm_source' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $end = Carbon::parse($this->input('end_date') ?: now()->toDateString());
            $start = Carbon::parse($this->input('start_date') ?: $end->copy()->subDays(29)->toDateString());
            if ($start->diffInDays($end, false) < 0 || $start->diffInDays($end) > 89) {
                $validator->errors()->add('start_date', 'Selecione um período de até 90 dias.');
            }
        }];
    }
}
