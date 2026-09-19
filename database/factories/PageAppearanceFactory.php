<?php

namespace Database\Factories;

use App\Models\PageAppearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageAppearance>
 */
class PageAppearanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_key' => 'home',
            'hero_enabled' => false,
            'hero_image_position' => 'center',
            'hero_overlay_preset' => 'institutional',
            'hero_overlay_opacity' => 75,
        ];
    }
}
