<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid, 'original_name' => 'motorista.png', 'stored_name' => $uuid.'.webp',
            'path' => 'courses/2026/09/'.$uuid.'.webp', 'bucket' => 'media', 'disk' => 'supabase',
            'original_mime_type' => 'image/png', 'mime_type' => 'image/webp',
            'original_extension' => 'png', 'extension' => 'webp',
            'original_size' => 2000000, 'size' => 200000, 'original_width' => 1200, 'original_height' => 800,
            'width' => 1200, 'height' => 800, 'reduction_percent' => 90,
            'alt_text' => 'Motorista', 'alt_is_custom' => false, 'is_decorative' => false,
            'status' => 'ready', 'visibility' => 'public',
        ];
    }
}
