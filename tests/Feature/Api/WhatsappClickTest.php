<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseModality;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WhatsappClickTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_click_and_returns_201(): void
    {
        $course = Course::factory()->create();
        $modality = CourseModality::factory()->for($course)->create();

        $response = $this->postJson('/api/whatsapp-clicks', [
            'course_id' => $course->id,
            'course_modality_id' => $modality->id,
            'source_url' => 'https://especializacondutor.com.br/cursos/mopp',
            'utm_source' => 'instagram',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('whatsapp_clicks', ['course_id' => $course->id, 'course_modality_id' => $modality->id, 'utm_source' => 'instagram']);
    }

    public function test_returns_422_when_modality_belongs_to_another_course(): void
    {
        $course = Course::factory()->create();
        $modality = CourseModality::factory()->create();

        $this->postJson('/api/whatsapp-clicks', [
            'course_id' => $course->id,
            'course_modality_id' => $modality->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['course_modality_id']);

        $this->assertDatabaseCount('whatsapp_clicks', 0);
    }
}
