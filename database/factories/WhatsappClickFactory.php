<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\WhatsappClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappClick>
 */
class WhatsappClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'source_url' => 'https://especializacondutor.com.br/cursos',
        ];
    }
}
