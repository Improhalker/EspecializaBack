<?php

namespace Tests\Feature\Api;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_endpoint_returns_only_published_testimonials_ordered_by_sort_order(): void
    {
        Testimonial::factory()->create(['name' => 'Rascunho', 'is_published' => false, 'sort_order' => 1]);
        $second = Testimonial::factory()->create(['name' => 'Segundo', 'is_published' => true, 'sort_order' => 2]);
        $first = Testimonial::factory()->create(['name' => 'Primeiro', 'is_published' => true, 'sort_order' => 1]);

        $this->getJson('/api/testimonials')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_public_endpoint_returns_empty_array_when_no_testimonials_are_published(): void
    {
        Testimonial::factory()->create(['is_published' => false]);

        $this->getJson('/api/testimonials')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_home_endpoint_exposes_published_testimonials_and_falls_back_to_an_empty_list(): void
    {
        $this->getJson('/api/home')->assertOk()->assertJsonCount(0, 'testimonials');

        $testimonial = Testimonial::factory()->create(['name' => 'Cliente satisfeito', 'is_published' => true]);
        Testimonial::factory()->create(['is_published' => false]);

        $this->getJson('/api/home')->assertOk()
            ->assertJsonCount(1, 'testimonials')
            ->assertJsonPath('testimonials.0.id', $testimonial->id)
            ->assertJsonPath('testimonials.0.name', 'Cliente satisfeito');
    }
}
