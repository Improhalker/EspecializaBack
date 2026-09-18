<?php

namespace App\Services;

use Illuminate\Support\Str;

class MediaAltText
{
    public static function suggest(?string $courseName = null, ?string $filename = null): string
    {
        if (filled($courseName)) {
            return Str::limit('Curso '.trim($courseName).' — Especializa Condutor', 500, '');
        }

        $name = pathinfo(str_replace('\\', '/', $filename ?? ''), PATHINFO_FILENAME);
        $name = preg_replace('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', '', $name);
        $name = preg_replace('/\b[a-f0-9]{20,}\b/i', '', $name);
        $name = trim(preg_replace('/[\s_-]+/u', ' ', $name));

        return preg_match('/\p{L}/u', $name)
            ? Str::limit(Str::ucfirst($name), 500, '')
            : 'Imagem do Especializa Condutor';
    }
}
