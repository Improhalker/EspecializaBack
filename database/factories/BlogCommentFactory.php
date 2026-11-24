<?php

namespace Database\Factories;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogComment>
 */
class BlogCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory()->published(),
            'author_name' => fake()->firstName(),
            'body' => fake()->sentence(12),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'moderated_at' => now()]);
    }
}
