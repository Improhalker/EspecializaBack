<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomeCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_endpoint_is_served_from_cache_after_the_first_request(): void
    {
        Course::factory()->create(['is_featured' => true, 'name' => 'MOPP']);

        $first = $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('featured_courses.0.name', 'MOPP');
        $this->assertStringContainsString('home-cache;desc="miss"', $first->headers->get('Server-Timing'));
        $this->assertPublicMaxAge($first->headers->get('Cache-Control'));

        $second = $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('featured_courses.0.name', 'MOPP');
        $this->assertStringContainsString('home-cache;desc="hit"', $second->headers->get('Server-Timing'));
    }

    public function test_courses_index_is_served_from_cache_after_the_first_request(): void
    {
        Course::factory()->create(['name' => 'MOPP', 'slug' => 'mopp']);

        $first = $this->getJson('/api/courses')->assertOk()
            ->assertJsonPath('data.0.name', 'MOPP');
        $this->assertStringContainsString('courses-cache;desc="miss"', $first->headers->get('Server-Timing'));
        $this->assertPublicMaxAge($first->headers->get('Cache-Control'));

        $second = $this->getJson('/api/courses')->assertOk()
            ->assertJsonPath('data.0.name', 'MOPP');
        $this->assertStringContainsString('courses-cache;desc="hit"', $second->headers->get('Server-Timing'));
    }

    public function test_course_detail_response_allows_brief_browser_caching(): void
    {
        Course::factory()->create(['slug' => 'mopp']);

        $response = $this->getJson('/api/courses/mopp')->assertOk();
        $this->assertPublicMaxAge($response->headers->get('Cache-Control'));
    }

    public function test_warm_command_pre_populates_the_home_and_courses_index_cache(): void
    {
        Course::factory()->create(['slug' => 'mopp']);

        $this->artisan('courses:warm-cache')->assertSuccessful();

        $home = $this->getJson('/api/home')->assertOk();
        $this->assertStringContainsString('home-cache;desc="hit"', $home->headers->get('Server-Timing'));

        $courses = $this->getJson('/api/courses')->assertOk();
        $this->assertStringContainsString('courses-cache;desc="hit"', $courses->headers->get('Server-Timing'));
    }

    private function assertPublicMaxAge(?string $cacheControl): void
    {
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age='.config('site.public_response_cache_seconds'), $cacheControl);
    }
}
