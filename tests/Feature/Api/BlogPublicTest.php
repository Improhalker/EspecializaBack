<?php

namespace Tests\Feature\Api;

use App\Models\BlogPost;
use App\Models\Course;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlogPublicTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listing_shows_only_published_posts_newest_first_with_pagination(): void
    {
        $older = BlogPost::factory()->published('2026-10-01 10:00:00')->create();
        $newer = BlogPost::factory()->published('2026-11-01 10:00:00')->create();
        BlogPost::factory()->create();
        BlogPost::factory()->unpublished()->create();

        $this->getJson('/api/blog/posts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $newer->slug)
            ->assertJsonPath('data.1.slug', $older->slug)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('data.0.body');

        $this->getJson('/api/blog/posts?per_page=1&page=2')->assertJsonPath('data.0.slug', $older->slug)->assertJsonPath('meta.last_page', 2);
    }

    public function test_drafts_and_unpublished_posts_return_not_found(): void
    {
        $draft = BlogPost::factory()->create();
        $removed = BlogPost::factory()->unpublished()->create();

        $this->getJson('/api/blog/posts/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/blog/posts/'.$removed->slug)->assertNotFound();
        $this->getJson('/api/blog/posts/nao-existe')->assertNotFound();
    }

    public function test_single_post_without_course_or_cover_is_complete(): void
    {
        $post = BlogPost::factory()->published()->create(['body' => '<p>Somente texto.</p>']);

        $this->getJson('/api/blog/posts/'.$post->slug)
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Somente texto.</p>')
            ->assertJsonPath('data.cover', null)
            ->assertJsonPath('data.course', null)
            ->assertJsonPath('data.cta', null)
            ->assertJsonPath('meta.related', [])
            ->assertJsonPath('meta.next', null);
    }

    public function test_course_cta_only_for_published_courses(): void
    {
        $course = Course::factory()->create(['name' => 'MOPP — Transporte de Produtos Perigosos', 'short_name' => 'MOPP', 'is_published' => true]);
        $hidden = Course::factory()->create(['is_published' => false]);
        $linked = BlogPost::factory()->published()->create(['course_id' => $course->id]);
        $whatsapp = BlogPost::factory()->published()->create(['course_id' => $course->id, 'cta_action' => 'whatsapp', 'cta_label' => 'Tire dúvidas sobre o MOPP']);
        $draftCourse = BlogPost::factory()->published()->create(['course_id' => $hidden->id]);
        $school = Course::factory()->create(['name' => 'Transporte Escolar', 'short_name' => 'Escolar', 'is_published' => true]);
        $schoolPost = BlogPost::factory()->published()->create(['course_id' => $school->id, 'cta_action' => 'whatsapp']);

        $this->getJson('/api/blog/posts/'.$linked->slug)
            ->assertJsonPath('data.course.slug', $course->slug)
            ->assertJsonPath('data.cta', ['action' => 'course', 'label' => 'Conheça o curso de MOPP']);
        $this->getJson('/api/blog/posts/'.$whatsapp->slug)
            ->assertJsonPath('data.cta', ['action' => 'whatsapp', 'label' => 'Tire dúvidas sobre o MOPP']);
        $this->getJson('/api/blog/posts/'.$schoolPost->slug)
            ->assertJsonPath('data.cta', ['action' => 'whatsapp', 'label' => 'Tire dúvidas sobre o curso de Transporte Escolar']);
        $this->getJson('/api/blog/posts/'.$draftCourse->slug)
            ->assertJsonPath('data.course', null)->assertJsonPath('data.cta', null);
    }

    public function test_related_posts_prioritize_course_then_category_and_exclude_offline_content(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $current = BlogPost::factory()->published('2026-11-10')->create(['course_id' => $course->id, 'category' => 'Formação']);
        $sameCourse = BlogPost::factory()->published('2026-10-01')->create(['course_id' => $course->id, 'category' => 'Outra']);
        $sameCategory = BlogPost::factory()->published('2026-11-05')->create(['category' => 'Formação']);
        BlogPost::factory()->published('2026-11-06')->create(['category' => 'Sem relação']);
        BlogPost::factory()->create(['course_id' => $course->id]);
        BlogPost::factory()->unpublished()->create(['category' => 'Formação']);

        $related = $this->getJson('/api/blog/posts/'.$current->slug)->json('meta.related');

        $this->assertSame([$sameCourse->slug, $sameCategory->slug], array_column($related, 'slug'));
    }

    public function test_next_post_follows_listing_order_with_stable_tie_breaker(): void
    {
        $date = '2026-11-01 12:00:00';
        $first = BlogPost::factory()->published($date)->create();
        $second = BlogPost::factory()->published($date)->create();
        $oldest = BlogPost::factory()->published('2026-10-01')->create();
        BlogPost::factory()->unpublished()->create();

        $this->getJson('/api/blog/posts/'.$second->slug)->assertJsonPath('meta.next.slug', $first->slug);
        $this->getJson('/api/blog/posts/'.$first->slug)->assertJsonPath('meta.next.slug', $oldest->slug);
        $this->getJson('/api/blog/posts/'.$oldest->slug)->assertJsonPath('meta.next', null);
    }

    public function test_suggested_courses_exclude_linked_and_unpublished_courses(): void
    {
        $linked = Course::factory()->create(['is_published' => true]);
        $other = Course::factory()->create(['is_published' => true]);
        Course::factory()->create(['is_published' => false]);
        $post = BlogPost::factory()->published()->create(['course_id' => $linked->id]);

        $this->assertSame([$other->slug], array_column($this->getJson('/api/blog/posts/'.$post->slug)->json('meta.suggested_courses'), 'slug'));
    }

    public function test_analytics_accepts_blog_paths(): void
    {
        $payload = fn (string $path): array => [
            'session_id' => fake()->uuid(),
            'page' => ['id' => fake()->uuid(), 'path' => $path, 'active_ms' => 1000, 'scroll_depth' => 10],
            'interactions' => [],
        ];
        config(['analytics.enabled' => true]);

        $this->postJson('/api/analytics/events', $payload('/blog/como-renovar-mopp'))->assertSuccessful();
        $this->postJson('/api/analytics/events', $payload('/blog'))->assertSuccessful();
    }
}
