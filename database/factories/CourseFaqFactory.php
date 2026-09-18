<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseFaq>
 */
class CourseFaqFactory extends Factory
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
            'question' => fake()->sentence(),
            'answer' => fake()->paragraph(),
            'sort_order' => 1,
        ];
    }
}
