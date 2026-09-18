<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_replace_course_cover_and_public_api_returns_optimized_url_and_alt(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $media = Media::factory()->create();
        $course = Course::factory()->create(['name' => 'MOPP', 'is_published' => true, 'cover_image_path' => 'https://old.example/image.jpg']);

        $this->putJson('/api/admin/courses/'.$course->id, [
            'name' => $course->name, 'slug' => $course->slug, 'summary' => $course->summary, 'cover_media_id' => $media->id,
        ])->assertOk()->assertJsonPath('data.cover_media_id', $media->id)
            ->assertJsonPath('data.cover.url', $media->deliveryUrl());

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'cover_media_id' => $media->id, 'cover_image_path' => null]);
        $this->getJson('/api/courses/'.$course->slug)->assertOk()
            ->assertJsonPath('data.cover.url', $media->deliveryUrl())
            ->assertJsonPath('data.cover.alt_text', 'Curso MOPP — Especializa Condutor')
            ->assertJsonMissingPath('data.cover.path');
    }

    public function test_legacy_cover_url_is_preserved(): void
    {
        $course = Course::factory()->create(['name' => 'Escolar', 'is_published' => true,
            'cover_image_path' => 'https://old.example/school.jpg']);

        $this->getJson('/api/courses/'.$course->slug)->assertOk()
            ->assertJsonPath('data.cover.url', 'https://old.example/school.jpg')
            ->assertJsonPath('data.cover.alt_text', 'Curso Escolar — Especializa Condutor');
    }

    public function test_same_media_has_independent_alt_text_for_each_course(): void
    {
        $media = Media::factory()->create();
        $first = Course::factory()->create(['name' => 'MOPP', 'cover_media_id' => $media->id,
            'is_published' => true, 'cover_alt_text' => 'Caminhão em treinamento']);
        $second = Course::factory()->create(['name' => 'Coletivo', 'cover_media_id' => $media->id, 'is_published' => true]);

        $this->getJson('/api/courses/'.$first->slug)->assertJsonPath('data.cover.alt_text', 'Caminhão em treinamento');
        $this->getJson('/api/courses/'.$second->slug)->assertJsonPath('data.cover.alt_text', 'Curso Coletivo — Especializa Condutor');
        $this->assertSame('Motorista', $media->fresh()->alt_text);
    }

    public function test_pending_deletion_cannot_be_attached_to_course(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $media = Media::factory()->create(['status' => 'deleting']);
        $course = Course::factory()->create();

        $this->putJson('/api/admin/courses/'.$course->id, [
            'name' => $course->name, 'slug' => $course->slug, 'summary' => $course->summary, 'cover_media_id' => $media->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('cover_media_id');

        $this->assertNull($course->fresh()->cover_media_id);
    }

    public function test_removing_cover_does_not_delete_reusable_media(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $media = Media::factory()->create();
        $course = Course::factory()->create(['cover_media_id' => $media->id]);

        $this->putJson('/api/admin/courses/'.$course->id, [
            'name' => $course->name, 'slug' => $course->slug, 'summary' => $course->summary,
            'cover_media_id' => null, 'cover_image_path' => null, 'cover_alt_text' => null,
        ])->assertOk()->assertJsonPath('data.cover', null);

        $this->assertModelExists($media);
        $this->assertNull($course->fresh()->cover_media_id);
    }
}
