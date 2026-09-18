<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseModality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseModality>
 */
class CourseModalityFactory extends Factory
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
            'name' => 'Formação',
            'workload' => 'Formação completa',
            'description' => fake()->paragraph(),
            'features' => [fake()->sentence()],
            'bonuses' => [],
            'price_mode' => 'hidden',
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
