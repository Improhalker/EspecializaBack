<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTestimonialTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_non_administrators_cannot_manage_testimonials(): void
    {
        $this->getJson('/api/admin/testimonials')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->getJson('/api/admin/testimonials')->assertForbidden();
        $this->postJson('/api/admin/testimonials', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_administrator_can_create_a_testimonial(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/testimonials', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.is_published', false);

        $this->assertDatabaseHas('testimonials', ['name' => 'Maria Souza', 'rating' => 5]);
    }

    public function test_creating_a_testimonial_validates_required_fields_and_rating_range(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/testimonials', ['rating' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'content', 'rating']);
    }

    public function test_avatar_media_id_must_reference_a_ready_public_media(): void
    {
        $this->actingAsAdministrator();
        $notReady = Media::factory()->create(['status' => 'uploading']);

        $this->postJson('/api/admin/testimonials', [...$this->payload(), 'avatar_media_id' => $notReady->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_media_id');

        $this->postJson('/api/admin/testimonials', [...$this->payload(), 'avatar_media_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_media_id');
    }

    public function test_administrator_can_update_a_testimonial_and_attach_an_avatar(): void
    {
        $this->actingAsAdministrator();
        $testimonial = Testimonial::factory()->create(['name' => 'Original']);
        $media = Media::factory()->create();

        $this->patchJson("/api/admin/testimonials/{$testimonial->id}", [...$this->payload(), 'name' => 'Atualizado', 'avatar_media_id' => $media->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Atualizado')
            ->assertJsonPath('data.avatar.url', $media->deliveryUrl());

        $this->assertDatabaseHas('testimonials', ['id' => $testimonial->id, 'name' => 'Atualizado', 'avatar_media_id' => $media->id]);
    }

    public function test_administrator_can_toggle_publication(): void
    {
        $this->actingAsAdministrator();
        $testimonial = Testimonial::factory()->create(['is_published' => false]);

        $this->patchJson("/api/admin/testimonials/{$testimonial->id}/publication", ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('testimonials', ['id' => $testimonial->id, 'is_published' => true]);
    }

    public function test_administrator_can_delete_a_testimonial(): void
    {
        $this->actingAsAdministrator();
        $testimonial = Testimonial::factory()->create();

        $this->deleteJson("/api/admin/testimonials/{$testimonial->id}")->assertNoContent();

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_listing_supports_search_status_filter_sort_and_pagination(): void
    {
        $this->actingAsAdministrator();
        Testimonial::factory()->create(['name' => 'Carlos Andrade', 'content' => 'Excelente curso', 'is_published' => true, 'sort_order' => 2]);
        $target = Testimonial::factory()->create(['name' => 'Fernanda Lima', 'content' => 'Muito bom', 'is_published' => true, 'sort_order' => 1]);
        Testimonial::factory()->create(['name' => 'Rascunho', 'is_published' => false, 'sort_order' => 3]);

        $this->getJson('/api/admin/testimonials?search=fernanda')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);

        $this->getJson('/api/admin/testimonials?status=draft')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Rascunho');

        $this->getJson('/api/admin/testimonials?sort=sort_order&direction=asc')
            ->assertOk()->assertJsonPath('data.0.id', $target->id);

        $this->getJson('/api/admin/testimonials?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
    }

    public function test_media_used_as_testimonial_avatar_cannot_be_deleted(): void
    {
        $this->actingAsAdministrator();
        $media = Media::factory()->create();
        $testimonial = Testimonial::factory()->create(['avatar_media_id' => $media->id, 'name' => 'Fernanda Lima']);

        $this->deleteJson('/api/admin/media/'.$media->id)->assertConflict()
            ->assertJsonPath('usage_count', 1)
            ->assertJsonPath('usages.0.type', 'testimonial')
            ->assertJsonPath('usages.0.id', $testimonial->id)
            ->assertJsonPath('usages.0.name', 'Fernanda Lima');

        $this->assertModelExists($media);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Maria Souza',
            'content' => 'O curso mudou minha carreira como motorista profissional.',
            'rating' => 5,
            'sort_order' => 1,
        ];
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => true,
            'must_change_password' => false,
        ]));
    }
}
