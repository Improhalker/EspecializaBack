<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageAppearanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_administrators_can_access_page_appearance_settings(): void
    {
        $this->getJson('/api/admin/page-appearances')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->patchJson('/api/admin/page-appearances/home', ['hero_enabled' => false])->assertForbidden();
        $this->assertDatabaseCount('page_appearances', 0);
    }

    public function test_registered_pages_are_listed_with_fallback_and_unregistered_keys_return_404(): void
    {
        $this->administrator();

        $this->getJson('/api/admin/page-appearances')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.page_key', 'home')
            ->assertJsonPath('data.0.hero.enabled', false)
            ->assertJsonPath('data.1.page_key', 'courses-index');
        $this->getJson('/api/admin/page-appearances/admin')->assertNotFound();
        $this->patchJson('/api/admin/page-appearances/admin', ['hero_enabled' => false])->assertNotFound();
        $this->assertDatabaseCount('page_appearances', 0);
    }

    public function test_administrator_can_save_page_heroes_and_public_endpoints_expose_them(): void
    {
        $desktop = Media::factory()->create();
        $mobile = Media::factory()->create();
        $this->administrator();

        $this->patchJson('/api/admin/page-appearances/home', [
            'hero_enabled' => true,
            'hero_media_id' => $desktop->id,
            'hero_mobile_media_id' => $mobile->id,
            'hero_image_position' => 'top',
            'hero_overlay_preset' => 'dark',
            'hero_overlay_opacity' => 65,
        ])->assertOk()->assertJsonPath('data.hero.enabled', true)
            ->assertJsonPath('data.hero.media.url', $desktop->deliveryUrl())
            ->assertJsonPath('data.hero.mobile_media.url', $mobile->deliveryUrl());

        $this->assertDatabaseHas('page_appearances', [
            'page_key' => 'home', 'hero_media_id' => $desktop->id,
            'hero_mobile_media_id' => $mobile->id, 'hero_overlay_opacity' => 65,
        ]);
        $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('hero.position', 'top')
            ->assertJsonPath('hero.overlay_preset', 'dark')
            ->assertJsonPath('hero.media.url', $desktop->deliveryUrl());

        $this->patchJson('/api/admin/page-appearances/courses-index', [
            'hero_enabled' => true, 'hero_media_id' => $desktop->id,
        ])->assertOk();
        $this->getJson('/api/courses')->assertOk()
            ->assertJsonPath('meta.hero.media.url', $desktop->deliveryUrl());
    }

    public function test_enabled_banner_requires_ready_public_media_and_rejects_invalid_preset(): void
    {
        $unavailable = Media::factory()->create(['status' => 'failed']);
        $this->administrator();

        $this->patchJson('/api/admin/page-appearances/home', ['hero_enabled' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('hero_media_id');
        $this->patchJson('/api/admin/page-appearances/home', [
            'hero_enabled' => true, 'hero_media_id' => $unavailable->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('hero_media_id');
        $this->patchJson('/api/admin/page-appearances/home', ['hero_overlay_preset' => 'orange'])
            ->assertUnprocessable()->assertJsonValidationErrors('hero_overlay_preset');
        $this->assertDatabaseCount('page_appearances', 0);
    }

    public function test_disabling_a_page_banner_restores_the_public_fallback(): void
    {
        $media = Media::factory()->create();
        $this->administrator();
        $this->patchJson('/api/admin/page-appearances/home', [
            'hero_enabled' => true, 'hero_media_id' => $media->id,
        ])->assertOk();

        $this->patchJson('/api/admin/page-appearances/home', [
            'hero_enabled' => false, 'hero_media_id' => null,
        ])->assertOk()->assertJsonPath('data.hero.enabled', false);

        $this->assertDatabaseHas('page_appearances', [
            'page_key' => 'home', 'hero_enabled' => false, 'hero_media_id' => null,
        ]);
        $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('hero.enabled', false)
            ->assertJsonPath('hero.media', null);
    }

    public function test_media_used_by_page_hero_cannot_be_deleted(): void
    {
        $media = Media::factory()->create();
        $this->administrator();
        $this->patchJson('/api/admin/page-appearances/home', [
            'hero_enabled' => true, 'hero_media_id' => $media->id,
        ])->assertOk();

        $this->getJson('/api/admin/media/'.$media->id.'/usages')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.type', 'page')
            ->assertJsonPath('data.0.page_key', 'home');
        $this->deleteJson('/api/admin/media/'.$media->id)->assertStatus(409)
            ->assertJsonPath('usage_count', 1);
        $this->assertModelExists($media);
    }

    private function administrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
    }
}
