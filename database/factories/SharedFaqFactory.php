<?php

namespace Database\Factories;

use App\Models\SharedFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedFaq>
 */
class SharedFaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
