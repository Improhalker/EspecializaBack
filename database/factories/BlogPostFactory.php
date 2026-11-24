<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(14),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'category' => null,
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
        ];
    }

    public function published(?string $publishedAt = null): static
    {
        return $this->state(fn (): array => [
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $publishedAt ?? now()->subDay(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'status' => BlogPost::STATUS_UNPUBLISHED,
            'published_at' => now()->subDays(2),
        ]);
    }
}
