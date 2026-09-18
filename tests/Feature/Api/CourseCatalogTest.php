<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseModality;
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
            ->assertJsonPath('data.faqs.0.id', $faq->id)
            ->assertJsonCount(1, 'data.modalities');
    }

    public function test_returns_404_for_unpublished_course(): void
    {
        Course::factory()->create(['slug' => 'curso-oculto', 'is_published' => false]);

        $this->getJson('/api/courses/curso-oculto')->assertNotFound();
    }
}
