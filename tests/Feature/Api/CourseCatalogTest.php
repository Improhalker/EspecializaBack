<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseModality;
use App\Models\Media;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_published_courses(): void
    {
        $publishedCourse = Course::factory()->create(['name' => 'Curso publicado', 'slug' => 'curso-publicado']);
        Course::factory()->create(['name' => 'Curso rascunho', 'slug' => 'curso-rascunho', 'is_published' => false]);

        $response = $this->getJson('/api/courses');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $publishedCourse->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_course_page_with_published_modalities_and_faqs(): void
    {
        $course = Course::factory()->create(['slug' => 'mopp']);
        $modality = CourseModality::factory()->for($course)->create(['name' => 'Atualização']);
        CourseModality::factory()->for($course)->create(['name' => 'Oculta', 'is_published' => false]);
        $faq = CourseFaq::factory()->for($course)->create();

        $response = $this->getJson('/api/courses/mopp');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.modalities.0.id', $modality->id)
            ->assertJsonPath('data.faqs.0.id', "course-{$faq->id}")
            ->assertJsonCount(1, 'data.modalities');
    }

    public function test_returns_404_for_unpublished_course(): void
    {
        Course::factory()->create(['slug' => 'curso-oculto', 'is_published' => false]);

        $this->getJson('/api/courses/curso-oculto')->assertNotFound();
    }

    public function test_course_detail_uses_a_local_cache_after_the_first_request(): void
    {
        Course::factory()->create(['slug' => 'curso-em-cache', 'summary' => 'Resumo inicial']);

        $first = $this->getJson('/api/courses/curso-em-cache')->assertOk()
            ->assertJsonPath('data.summary', 'Resumo inicial');
        $this->assertStringContainsString('course-cache;desc="miss"', $first->headers->get('Server-Timing'));

        $second = $this->getJson('/api/courses/curso-em-cache')->assertOk()
            ->assertJsonPath('data.summary', 'Resumo inicial');
        $this->assertStringContainsString('course-cache;desc="hit"', $second->headers->get('Server-Timing'));
    }

    public function test_cached_media_urls_use_the_configured_api_origin(): void
    {
        config()->set('app.url', 'https://api.especializacondutor.com.br');
        $media = Media::factory()->create(['status' => 'ready', 'visibility' => 'public']);
        Course::factory()->create(['slug' => 'curso-com-capa', 'cover_media_id' => $media->id]);

        $this->withHeader('Host', 'outro-host.exemplo')
            ->getJson('/api/courses/curso-com-capa')
            ->assertOk()
            ->assertJsonPath('data.cover.url', 'https://api.especializacondutor.com.br/api/media/'.$media->uuid);
    }
}
