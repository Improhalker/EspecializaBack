<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseHeroTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_set_independent_course_cover_and_hero(): void
    {
        $cover = Media::factory()->create();
        $banner = Media::factory()->create();
        $course = Course::factory()->create(['cover_media_id' => $cover->id, 'is_published' => true]);
        $this->administrator();

        $this->putJson('/api/admin/courses/'.$course->id, [
            'name' => $course->name,
            'slug' => $course->slug,
            'summary' => $course->summary,
            'cover_media_id' => $cover->id,
            'hero_enabled' => true,
            'hero_media_id' => $banner->id,
            'hero_image_position' => 'right',
            'hero_overlay_preset' => 'soft',
            'hero_overlay_opacity' => 80,
        ])->assertOk()->assertJsonPath('data.cover_media_id', $cover->id)
            ->assertJsonPath('data.hero_media_id', $banner->id);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id, 'cover_media_id' => $cover->id,
            'hero_media_id' => $banner->id, 'hero_overlay_opacity' => 80,
        ]);
        $this->getJson('/api/courses/'.$course->slug)->assertOk()
            ->assertJsonPath('data.cover.url', $cover->deliveryUrl())
            ->assertJsonPath('data.hero.media.url', $banner->deliveryUrl())
            ->assertJsonPath('data.hero.position', 'right');

        $this->getJson('/api/admin/media/'.$banner->id.'/usages')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.role', 'Banner do curso');
        $this->deleteJson('/api/admin/media/'.$banner->id)->assertStatus(409);
    }

    public function test_course_hero_rejects_unavailable_image_and_keeps_blue_fallback(): void
    {
        $media = Media::factory()->create(['visibility' => 'private']);
        $course = Course::factory()->create(['is_published' => true]);
        $this->administrator();

        $this->putJson('/api/admin/courses/'.$course->id, [
            'name' => $course->name, 'slug' => $course->slug,
            'summary' => $course->summary, 'hero_enabled' => true,
            'hero_media_id' => $media->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('hero_media_id');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'hero_media_id' => null]);
        $this->getJson('/api/courses/'.$course->slug)->assertOk()
            ->assertJsonPath('data.hero.enabled', false)
            ->assertJsonPath('data.hero.media', null);
    }

    private function administrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
    }
}
