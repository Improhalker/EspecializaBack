<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.supabase_url' => 'https://storage.example.test',
            'media.supabase_key' => 'sb_secret_test', 'media.bucket' => 'media',
            'media.max_dimension' => 600]);
        Http::preventStrayRequests();
    }

    public function test_missing_authentication_returns_401(): void
    {
        $this->getJson('/api/admin/media')->assertUnauthorized();
    }

    public function test_non_administrator_cannot_upload_and_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        Http::fake(['storage.example.test/*' => Http::response([], 200)]);

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('foto.jpg')])->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_initial_password_must_be_changed_before_using_media(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => true]));

        $this->getJson('/api/admin/v1/media')->assertStatus(423);
    }

    #[DataProvider('rasterFormats')]
    public function test_valid_raster_is_converted_and_resized_before_storage(string $extension): void
    {
        $this->admin();
        Http::fake(['storage.example.test/storage/v1/object/media/*' => Http::response(['Key' => 'media/image'], 200)]);
        $file = UploadedFile::fake()->image('caminhao.'.$extension, 1200, 600);

        $response = $this->postJson('/api/admin/media', ['file' => $file, 'course_name' => 'MOPP']);

        $response->assertCreated()->assertJsonPath('data.extension', 'webp')
            ->assertJsonPath('data.width', 600)->assertJsonPath('data.height', 300)
            ->assertJsonPath('data.alt_text', 'Curso MOPP — Especializa Condutor')
            ->assertJsonMissingPath('data.path')->assertJsonMissingPath('data.bucket');
        $media = Media::query()->findOrFail($response->json('data.id'));
        $this->assertSame('ready', $media->status);
        $this->assertSame($extension, $media->original_extension);
        $this->assertSame(1200, $media->original_width);
        Http::assertSent(function (Request $request) use ($media): bool {
            $dimensions = getimagesizefromstring($request->body());

            return $request->method() === 'POST' && str_starts_with($request->body(), 'RIFF')
                && $dimensions[0] === 600 && strlen($request->body()) === $media->size
                && $request->hasHeader('apikey', 'sb_secret_test')
                && ! $request->hasHeader('Authorization');
        });
    }

    public static function rasterFormats(): array
    {
        return ['jpeg' => ['jpg'], 'png' => ['png'], 'webp' => ['webp']];
    }

    public function test_safe_svg_is_preserved_and_resized(): void
    {
        $this->admin();
        Http::fake(['storage.example.test/storage/v1/object/media/*' => Http::response([], 200)]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="600"><rect width="1200" height="600" fill="#0e3459"/></svg>';

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->createWithContent('marca.svg', $svg)])
            ->assertCreated()->assertJsonPath('data.extension', 'svg')
            ->assertJsonPath('data.width', 600)->assertJsonPath('data.height', 300);

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'viewBox="0 0 1200 600"'));
    }

    #[DataProvider('unsafeSvgContents')]
    public function test_unsafe_svg_returns_422_without_writing_storage(string $content): void
    {
        $this->admin();
        Http::fake(['storage.example.test/*' => Http::response([], 200)]);

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->createWithContent('unsafe.svg', $content)])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        Http::assertNothingSent();
        $this->assertDatabaseCount('media', 0);
    }

    public static function unsafeSvgContents(): array
    {
        $open = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">';

        return [
            'script' => [$open.'<script>alert(1)</script></svg>'],
            'event' => ['<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" onload="alert(1)"/>'],
            'external image' => [$open.'<image href="https://evil.example/a"/></svg>'],
            'foreign object' => [$open.'<foreignObject><p>HTML</p></foreignObject></svg>'],
            'css' => [$open.'<style>@import "https://evil.example";</style></svg>'],
            'entity' => ['<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'.$open.'<title>&xxe;</title></svg>'],
            'external paint' => [$open.'<path fill="url(https://evil.example)" d="M0 0"/></svg>'],
            'encoded event' => [$open.'<rect fill="url(&#104;ttps://evil.example)" width="10" height="10"/></svg>'],
        ];
    }

    public function test_wrong_extension_returns_422(): void
    {
        $this->admin();
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->createWithContent('foto.php', 'not an image')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_false_image_content_returns_422(): void
    {
        $this->admin();
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo "bad";')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_image_with_mismatched_extension_returns_422(): void
    {
        $this->admin();
        $png = UploadedFile::fake()->image('actual.png')->getContent();
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->createWithContent('foto.jpg', $png)])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', 'O conteúdo da imagem não corresponde à extensão do arquivo.');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_oversized_original_returns_422(): void
    {
        $this->admin();
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('foto.png')->size(10241)])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_excessive_pixel_count_returns_422_before_decoding(): void
    {
        $this->admin();
        config(['media.max_pixels' => 100]);
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('foto.png', 20, 20)])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_listing_searches_sorts_and_paginates_on_server(): void
    {
        $this->admin();
        Media::factory()->create(['original_name' => 'onibus-b.png', 'size' => 200]);
        $first = Media::factory()->create(['original_name' => 'onibus-a.png', 'size' => 100]);
        Media::factory()->create(['original_name' => 'ambulancia.png']);

        $this->getJson('/api/admin/v1/media?search=onibus&sort=size&direction=asc&per_page=1&page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('upload.max_size_bytes', 10485760);
    }

    public function test_sort_injection_returns_422(): void
    {
        $this->admin();
        $this->getJson('/api/admin/media?sort=path;DROP%20TABLE%20media&per_page=1000')
            ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'per_page']);
    }

    public function test_used_media_cannot_be_deleted_and_returns_course_reference(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        $course = Course::factory()->create(['cover_media_id' => $media->id, 'name' => 'MOPP', 'is_published' => false]);
        Http::fake(['storage.example.test/*' => Http::response([], 200)]);

        $this->deleteJson('/api/admin/media/'.$media->id)->assertConflict()
            ->assertJsonPath('usages.0.id', $course->id)->assertJsonPath('usages.0.name', 'MOPP');

        $this->assertModelExists($media);
        Http::assertNothingSent();
    }

    public function test_unused_media_is_deleted_from_storage_and_database(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/storage/v1/object/media' => Http::response([], 200)]);

        $this->deleteJson('/api/admin/media/'.$media->id)->assertNoContent();

        $this->assertModelMissing($media);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request['prefixes'] === [$media->path]);
    }

    public function test_storage_deletion_failure_keeps_record_for_retry_and_prevents_delivery(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/storage/v1/object/media' => Http::response([], 503)]);

        $this->deleteJson('/api/admin/media/'.$media->id)->assertServiceUnavailable();

        $this->assertSame('deleting', $media->fresh()->status);
        $this->assertNull($media->fresh()->deliveryUrl());
        Http::assertSentCount(1);
    }

    public function test_pending_deletion_can_be_retried(): void
    {
        $this->admin();
        $media = Media::factory()->create(['status' => 'deleting']);
        Http::fake(['storage.example.test/storage/v1/object/media' => Http::response([], 200)]);

        $this->deleteJson('/api/admin/media/'.$media->id)->assertNoContent();

        $this->assertModelMissing($media);
        Http::assertSentCount(1);
    }

    public function test_failed_upload_cleans_storage_and_does_not_leave_ready_media(): void
    {
        $this->admin();
        Http::fake([
            'storage.example.test/storage/v1/object/media/*' => Http::response([], 503),
            'storage.example.test/storage/v1/object/media' => Http::response([], 200),
        ]);

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('foto.png')])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('media', 0);
        Http::assertSentCount(2);
    }

    public function test_repeated_upload_key_reuses_completed_upload(): void
    {
        $this->admin();
        $key = '11111111-1111-4111-8111-111111111111';
        $media = Media::factory()->create(['upload_key' => $key]);
        Http::fake(['storage.example.test/*' => Http::response([], 200)]);

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('foto.png'), 'upload_key' => $key])
            ->assertOk()->assertJsonPath('data.id', $media->id);

        Http::assertNothingSent();
        $this->assertDatabaseCount('media', 1);
    }

    public function test_alt_text_can_be_edited_without_mutating_internal_metadata(): void
    {
        $this->admin();
        $media = Media::factory()->create();

        $this->patchJson('/api/admin/media/'.$media->id, [
            'alt_text' => 'Ônibus azul na estrada', 'is_decorative' => false, 'path' => 'evil.php',
        ])->assertOk()->assertJsonPath('data.alt_text', 'Ônibus azul na estrada');

        $this->assertDatabaseHas('media', ['id' => $media->id, 'path' => $media->path, 'alt_is_custom' => true]);
    }

    public function test_public_content_requires_alt_text(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        $this->patchJson('/api/admin/media/'.$media->id, ['alt_text' => ' ', 'is_decorative' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('alt_text');
    }

    public function test_decorative_media_can_omit_alt_text(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        $this->patchJson('/api/admin/media/'.$media->id, ['alt_text' => '', 'is_decorative' => true])
            ->assertOk()->assertJsonPath('data.alt_text', null);
    }

    public function test_course_cover_cannot_be_marked_decorative(): void
    {
        $this->admin();
        $media = Media::factory()->create();
        Course::factory()->create(['cover_media_id' => $media->id]);
        $this->patchJson('/api/admin/media/'.$media->id, ['is_decorative' => true])->assertConflict();
    }

    public function test_delivery_returns_bytes_with_secure_headers_and_no_storage_redirect(): void
    {
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/storage/v1/object/authenticated/media/*' => Http::response('image-bytes', 200)]);

        $this->get('/api/media/'.$media->uuid)->assertOk()->assertContent('image-bytes')
            ->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Location');

        Http::assertSentCount(1);
    }

    public function test_cached_bytes_are_reused_only_while_media_remains_public(): void
    {
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/storage/v1/object/authenticated/media/*' => Http::response('image-bytes', 200)]);

        $first = $this->get('/api/media/'.$media->uuid)->assertOk()->assertContent('image-bytes');
        $second = $this->get('/api/media/'.$media->uuid)->assertOk()->assertContent('image-bytes');
        $this->assertStringContainsString('media-cache;desc="miss"', $first->headers->get('Server-Timing'));
        $this->assertStringContainsString('media-cache;desc="hit"', $second->headers->get('Server-Timing'));
        Http::assertSentCount(1);

        $media->update(['visibility' => 'private']);
        $this->get('/api/media/'.$media->uuid)->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_warm_command_populates_media_cache_before_the_first_visit(): void
    {
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/storage/v1/object/authenticated/media/*' => Http::response('image-bytes', 200)]);

        $this->artisan('media:warm-cache')->assertSuccessful();
        $this->get('/api/media/'.$media->uuid)->assertOk()->assertContent('image-bytes');

        Http::assertSentCount(1);
    }

    public function test_private_media_cannot_be_delivered_publicly(): void
    {
        $media = Media::factory()->create(['visibility' => 'private']);
        $this->get('/api/media/'.$media->uuid)->assertNotFound();
    }

    public function test_conditional_delivery_does_not_download_same_bytes_again(): void
    {
        $media = Media::factory()->create();
        Http::fake(['storage.example.test/*' => Http::response([], 200)]);

        $this->get('/api/media/'.$media->uuid, ['If-None-Match' => '"'.$media->uuid.'"'])->assertStatus(304);

        Http::assertNothingSent();
    }

    public function test_prepare_creates_restricted_private_bucket(): void
    {
        Http::fake([
            'storage.example.test/storage/v1/bucket/media' => Http::response(['statusCode' => '404'], 400),
            'storage.example.test/storage/v1/bucket' => Http::response(['name' => 'media'], 200),
        ]);

        $this->artisan('media:prepare')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' &&
            $request['public'] === false && $request['allowed_mime_types'] === ['image/webp', 'image/svg+xml']);
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
    }
}
