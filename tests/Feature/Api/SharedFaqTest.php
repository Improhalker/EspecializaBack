<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\SharedFaq;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SharedFaqTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_all_courses_faq_is_automatically_applied_to_every_published_course(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        SharedFaq::factory()->create([
            'question' => 'Como funciona o suporte?',
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
        ]);

        $this->getJson("/api/courses/{$course->slug}")->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'Como funciona o suporte?')
            ->assertJsonPath('data.faqs.0.source', 'shared');
    }

    public function test_selected_courses_faq_only_appears_on_associated_courses(): void
    {
        $associated = Course::factory()->create(['is_published' => true]);
        $other = Course::factory()->create(['is_published' => true]);
        $faq = SharedFaq::factory()->create([
            'application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES,
            'is_published' => true,
        ]);
        $faq->courses()->sync([$associated->id]);

        $this->getJson("/api/courses/{$associated->slug}")->assertOk()->assertJsonCount(1, 'data.faqs');
        $this->getJson("/api/courses/{$other->slug}")->assertOk()->assertJsonCount(0, 'data.faqs');
    }

    public function test_unpublished_shared_faq_never_appears_publicly(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        SharedFaq::factory()->create([
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => false,
        ]);

        $this->getJson("/api/courses/{$course->slug}")->assertOk()->assertJsonCount(0, 'data.faqs');
    }

    public function test_composed_list_orders_shared_faqs_before_individual_course_faqs(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        SharedFaq::factory()->create([
            'question' => 'Pergunta compartilhada',
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
            'sort_order' => 1,
        ]);
        CourseFaq::factory()->for($course)->create(['question' => 'Pergunta individual', 'sort_order' => 1]);

        $this->getJson("/api/courses/{$course->slug}")->assertOk()
            ->assertJsonCount(2, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'Pergunta compartilhada')
            ->assertJsonPath('data.faqs.0.source', 'shared')
            ->assertJsonPath('data.faqs.1.question', 'Pergunta individual')
            ->assertJsonPath('data.faqs.1.source', 'course');
    }

    public function test_individual_faq_wins_deduplication_against_a_shared_faq_with_the_same_normalized_question(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        SharedFaq::factory()->create([
            'question' => 'Como funciona a matrícula?',
            'answer' => 'Resposta genérica da biblioteca.',
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
        ]);
        CourseFaq::factory()->for($course)->create([
            'question' => '  COMO FUNCIONA A MATRÍCULA  ',
            'answer' => 'Resposta específica deste curso.',
        ]);

        $this->getJson("/api/courses/{$course->slug}")->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.source', 'course')
            ->assertJsonPath('data.faqs.0.answer', 'Resposta específica deste curso.');
    }

    public function test_composed_ids_are_prefixed_to_avoid_collisions_between_sources(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $shared = SharedFaq::factory()->create([
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
        ]);
        $individual = CourseFaq::factory()->for($course)->create();

        $this->getJson("/api/courses/{$course->slug}")->assertOk()
            ->assertJsonPath('data.faqs.0.id', "shared-{$shared->id}")
            ->assertJsonPath('data.faqs.1.id', "course-{$individual->id}");
    }

    public function test_home_global_faq_endpoint_remains_unaffected_by_shared_faqs(): void
    {
        SharedFaq::factory()->create([
            'application_mode' => SharedFaq::APPLICATION_ALL_COURSES,
            'is_published' => true,
        ]);

        $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('faqs.0.question', 'Como faço a matrícula?');
    }
}
