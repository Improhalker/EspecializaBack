<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'content' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'is_published' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
