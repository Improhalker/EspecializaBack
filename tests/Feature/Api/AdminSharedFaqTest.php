<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\SharedFaq;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSharedFaqTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_non_administrators_cannot_manage_shared_faqs(): void
    {
        $this->getJson('/api/admin/faqs')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->getJson('/api/admin/faqs')->assertForbidden();
        $this->postJson('/api/admin/faqs', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('shared_faqs', 0);
    }

    public function test_administrator_can_create_a_faq_applied_to_all_courses(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/faqs', $this->payload(['application_mode' => 'all_courses']))
            ->assertCreated()
            ->assertJsonPath('data.application_mode', 'all_courses')
            ->assertJsonCount(0, 'data.course_ids');

        $this->assertDatabaseHas('shared_faqs', ['question' => 'Como funciona o suporte?']);
        $this->assertDatabaseCount('course_shared_faq', 0);
    }

    public function test_administrator_can_create_a_faq_applied_to_selected_courses(): void
    {
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/faqs', $this->payload([
            'application_mode' => 'selected_courses',
            'course_ids' => [$courseA->id, $courseB->id],
        ]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.course_ids');

        $this->assertDatabaseCount('course_shared_faq', 2);
    }

    public function test_selected_courses_mode_requires_at_least_one_valid_course(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/faqs', $this->payload(['application_mode' => 'selected_courses', 'course_ids' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('course_ids');

        $this->postJson('/api/admin/faqs', $this->payload(['application_mode' => 'selected_courses', 'course_ids' => [999999]]))
            ->assertUnprocessable()->assertJsonValidationErrors('course_ids.0');
    }

    public function test_creating_a_faq_validates_required_fields_and_application_mode(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/faqs', ['application_mode' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question', 'answer', 'application_mode']);
    }

    public function test_administrator_can_edit_course_associations(): void
    {
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();
        $faq = SharedFaq::factory()->create(['application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES]);
        $faq->courses()->sync([$courseA->id]);
        $this->actingAsAdministrator();

        $this->patchJson("/api/admin/faqs/{$faq->id}", $this->payload([
            'application_mode' => 'selected_courses',
            'course_ids' => [$courseB->id],
        ]))->assertOk()->assertJsonCount(1, 'data.course_ids')->assertJsonPath('data.course_ids.0', $courseB->id);

        $this->assertDatabaseHas('course_shared_faq', ['course_id' => $courseB->id, 'shared_faq_id' => $faq->id]);
        $this->assertDatabaseMissing('course_shared_faq', ['course_id' => $courseA->id, 'shared_faq_id' => $faq->id]);
    }

    public function test_switching_to_all_courses_clears_existing_associations(): void
    {
        $course = Course::factory()->create();
        $faq = SharedFaq::factory()->create(['application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES]);
        $faq->courses()->sync([$course->id]);
        $this->actingAsAdministrator();

        $this->patchJson("/api/admin/faqs/{$faq->id}", $this->payload(['application_mode' => 'all_courses']))
            ->assertOk()->assertJsonCount(0, 'data.course_ids');

        $this->assertDatabaseCount('course_shared_faq', 0);
    }

    public function test_administrator_can_toggle_publication(): void
    {
        $this->actingAsAdministrator();
        $faq = SharedFaq::factory()->create(['is_published' => false]);

        $this->patchJson("/api/admin/faqs/{$faq->id}/publication", ['is_published' => true])
            ->assertOk()->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('shared_faqs', ['id' => $faq->id, 'is_published' => true]);
    }

    public function test_deleting_a_shared_faq_only_removes_its_associations(): void
    {
        $course = Course::factory()->create();
        $faq = SharedFaq::factory()->create(['application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES]);
        $faq->courses()->sync([$course->id]);
        $this->actingAsAdministrator();

        $this->deleteJson("/api/admin/faqs/{$faq->id}")->assertNoContent();

        $this->assertDatabaseMissing('shared_faqs', ['id' => $faq->id]);
        $this->assertDatabaseCount('course_shared_faq', 0);
        $this->assertModelExists($course);
    }

    public function test_deleting_a_course_removes_its_shared_faq_associations_but_not_the_shared_faq(): void
    {
        $course = Course::factory()->create();
        $faq = SharedFaq::factory()->create(['application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES]);
        $faq->courses()->sync([$course->id]);
        $this->actingAsAdministrator();

        $this->deleteJson("/api/admin/courses/{$course->id}")->assertNoContent();

        $this->assertModelExists($faq);
        $this->assertDatabaseCount('course_shared_faq', 0);
    }

    public function test_listing_supports_search_status_application_mode_course_filter_and_pagination(): void
    {
        $course = Course::factory()->create();
        $matching = SharedFaq::factory()->create(['question' => 'Sobre matrícula', 'is_published' => true, 'application_mode' => SharedFaq::APPLICATION_ALL_COURSES]);
        $draft = SharedFaq::factory()->create(['question' => 'Rascunho', 'is_published' => false, 'application_mode' => SharedFaq::APPLICATION_ALL_COURSES]);
        $selected = SharedFaq::factory()->create(['question' => 'Somente curso X', 'is_published' => true, 'application_mode' => SharedFaq::APPLICATION_SELECTED_COURSES]);
        $selected->courses()->sync([$course->id]);
        $this->actingAsAdministrator();

        $this->getJson('/api/admin/faqs?search=matrícula')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);

        $this->getJson('/api/admin/faqs?status=draft')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft->id);

        $this->getJson('/api/admin/faqs?application_mode=selected_courses')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $selected->id);

        $this->getJson("/api/admin/faqs?course_id={$course->id}")
            ->assertOk()->assertJsonCount(3, 'data');

        $this->getJson('/api/admin/faqs?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
    }

    public function test_prevents_duplicate_course_ids_in_the_same_request(): void
    {
        $course = Course::factory()->create();
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/faqs', $this->payload([
            'application_mode' => 'selected_courses',
            'course_ids' => [$course->id, $course->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('course_ids.0');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'question' => 'Como funciona o suporte?',
            'answer' => 'Nossa equipe atende pelo WhatsApp em horário comercial.',
            'application_mode' => 'all_courses',
            'sort_order' => 1,
        ], $overrides);
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => true,
            'must_change_password' => false,
        ]));
    }
}
