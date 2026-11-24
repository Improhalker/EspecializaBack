<?php

namespace Tests\Feature\Api;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminBlogPostTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Quando fazer a atualização do MOPP',
            'excerpt' => 'Entenda o prazo de validade do curso e como se organizar.',
            'body' => '<h2>Prazo</h2><p>O curso tem validade de cinco anos.</p>',
            'category' => 'Atualização',
            ...$overrides,
        ];
    }

    public function test_guests_and_non_administrators_cannot_manage_posts(): void
    {
        $this->getJson('/api/admin/blog/posts')->assertUnauthorized();
        $this->postJson('/api/admin/blog/preview', ['body' => '<p>x</p>'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->getJson('/api/admin/blog/posts')->assertForbidden();
        $this->postJson('/api/admin/blog/posts', $this->payload())->assertForbidden();
        $this->postJson('/api/admin/blog/preview', ['body' => '<p>x</p>'])->assertForbidden();
        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_new_post_starts_as_draft_with_generated_unique_slug_even_if_status_is_sent(): void
    {
        $this->actingAsAdministrator();
        BlogPost::factory()->create(['slug' => 'quando-fazer-a-atualizacao-do-mopp']);

        $this->postJson('/api/admin/blog/posts', $this->payload(['status' => 'published', 'published_at' => now()->toISOString()]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.slug', 'quando-fazer-a-atualizacao-do-mopp-2');
    }

    public function test_body_is_sanitized_on_save_and_must_keep_content(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/blog/posts', $this->payload(['body' => '<p onclick="x()">Texto</p><script>alert(1)</script>']))
            ->assertCreated()
            ->assertJsonPath('data.body', '<p>Texto</p>');

        $this->postJson('/api/admin/blog/posts', $this->payload(['title' => 'Outro', 'body' => '<script>alert(1)</script>']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_preview_uses_the_same_sanitization_without_saving(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/api/admin/blog/preview', ['body' => '<h1>Olá</h1><img src="x" onerror="alert(1)"><a href="javascript:x">l</a>'])
            ->assertOk()
            ->assertJsonPath('data.html', '<h2>Olá</h2><a>l</a>')
            ->assertJsonFragment(['imagem fora da biblioteca de mídia']);

        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_slug_validation_and_uniqueness_on_update(): void
    {
        $this->actingAsAdministrator();
        $post = BlogPost::factory()->create(['slug' => 'meu-artigo']);
        BlogPost::factory()->create(['slug' => 'ocupado']);

        $this->putJson("/api/admin/blog/posts/{$post->id}", $this->payload(['slug' => 'Com Espaço']))->assertJsonValidationErrors('slug');
        $this->putJson("/api/admin/blog/posts/{$post->id}", $this->payload(['slug' => 'ocupado']))->assertJsonValidationErrors('slug');
        $this->putJson("/api/admin/blog/posts/{$post->id}", $this->payload(['slug' => 'meu-artigo']))->assertOk()->assertJsonPath('data.slug', 'meu-artigo');
    }

    public function test_publication_and_unpublication_keep_the_original_publication_date(): void
    {
        $this->actingAsAdministrator();
        $post = BlogPost::factory()->create();

        $published = $this->patchJson("/api/admin/blog/posts/{$post->id}/publication", ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.is_published', true)->json('data.published_at');
        $this->assertNotNull($published);

        $this->patchJson("/api/admin/blog/posts/{$post->id}/publication", ['status' => 'unpublished'])
            ->assertOk()->assertJsonPath('data.status', 'unpublished')->assertJsonPath('data.is_published', false);
        $this->getJson('/api/blog/posts/'.$post->slug)->assertNotFound();

        $this->travel(2)->days();
        $this->patchJson("/api/admin/blog/posts/{$post->id}/publication", ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.published_at', $published);

        $this->patchJson("/api/admin/blog/posts/{$post->id}/publication", ['status' => 'draft'])->assertJsonValidationErrors('status');
    }

    public function test_cover_and_body_images_are_linked_and_protected_from_deletion(): void
    {
        $this->actingAsAdministrator();
        config(['app.url' => 'http://localhost:8000']);
        $cover = Media::factory()->create();
        $inline = Media::factory()->create();
        $this->mock(MediaStorageService::class, fn ($mock) => $mock->shouldReceive('delete')->never());

        $id = $this->postJson('/api/admin/blog/posts', $this->payload([
            'cover_media_id' => $cover->id,
            'cover_alt_text' => 'Caminhão-tanque na rodovia',
            'body' => '<p>Texto</p><img src="http://localhost:8000/api/media/'.$inline->uuid.'" alt="Placa de produto perigoso">',
        ]))->assertCreated()
            ->assertJsonPath('data.cover.alt_text', 'Caminhão-tanque na rodovia')
            ->json('data.id');

        $this->assertDatabaseHas('blog_post_media', ['blog_post_id' => $id, 'media_id' => $inline->id]);

        $this->deleteJson("/api/admin/media/{$cover->id}")->assertConflict()->assertJsonPath('usages.0.role', 'Capa do artigo');
        $this->deleteJson("/api/admin/media/{$inline->id}")->assertConflict()->assertJsonPath('usages.0.type', 'blog_post');
        $this->getJson("/api/admin/media/{$inline->id}")->assertJsonPath('data.usage_count', 1);

        $this->putJson("/api/admin/blog/posts/{$id}", $this->payload())->assertOk();
        $this->assertDatabaseMissing('blog_post_media', ['blog_post_id' => $id]);
    }

    public function test_unavailable_cover_is_rejected(): void
    {
        $this->actingAsAdministrator();
        $media = Media::factory()->create(['status' => 'uploading']);

        $this->postJson('/api/admin/blog/posts', $this->payload(['cover_media_id' => $media->id]))
            ->assertJsonValidationErrors('cover_media_id');
    }

    public function test_listing_filters_by_status_and_lists_categories(): void
    {
        $this->actingAsAdministrator();
        BlogPost::factory()->published()->create(['category' => 'Legislação']);
        BlogPost::factory()->create(['category' => 'Dicas']);

        $this->getJson('/api/admin/blog/posts?status=published')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/blog/posts')->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/blog/categories')->assertExactJson(['data' => ['Dicas', 'Legislação']]);
    }

    public function test_course_link_is_optional_and_must_exist(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create();

        $this->postJson('/api/admin/blog/posts', $this->payload(['course_id' => 99999]))->assertJsonValidationErrors('course_id');
        $this->postJson('/api/admin/blog/posts', $this->payload(['course_id' => $course->id, 'cta_action' => 'whatsapp']))
            ->assertCreated()->assertJsonPath('data.course.id', $course->id)->assertJsonPath('data.cta_action', 'whatsapp');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
