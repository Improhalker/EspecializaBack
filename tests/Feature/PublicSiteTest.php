<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function prepareSite(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('site/index.html', '<!doctype html><html lang="pt-BR"><head><!-- public-seo:start --><title>Default</title><!-- public-seo:end --></head><body><div id="app"></div></body></html>');
        config(['site.url' => 'https://especializa.example.com', 'site.indexable' => true, 'site.frontend_build_path' => Storage::disk('local')->path('site')]);
    }

    public static function publicPages(): array
    {
        return [
            ['/', 'Cursos para motoristas'],
            ['/cursos?utm_source=qa&categoria=x', 'Cursos'],
            ['/quem-somos', 'Quem somos'],
            ['/politica-de-privacidade', 'Política de Privacidade'],
            ['/termos-de-uso', 'Termos de Uso'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_renders_initial_metadata_without_tracking_parameters(string $path, string $title): void
    {
        $this->prepareSite();

        $response = $this->get('https://especializa.example.com'.$path);

        $response->assertSee('<title>'.$title.' | Especializa Condutor</title>', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertDontSee('utm_source', false)
            ->assertDontSee('categoria=', false)
            ->assertHeader('X-Robots-Tag', 'index, follow');
    }

    public function test_course_uses_configured_seo_and_cover_in_initial_html(): void
    {
        $this->prepareSite();
        Course::factory()->create(['slug' => 'transporte', 'meta_title' => 'Título cadastrado | Especializa Condutor', 'meta_description' => 'Descrição cadastrada & segura', 'cover_image_path' => 'https://images.example.com/capa.webp']);

        $this->get('https://especializa.example.com/cursos/transporte?utm_medium=qa')
            ->assertSee('<title>Título cadastrado | Especializa Condutor</title>', false)
            ->assertSee('Descrição cadastrada &amp; segura', false)
            ->assertSee('property="og:image" content="https://images.example.com/capa.webp"', false)
            ->assertSee('rel="canonical" href="https://especializa.example.com/cursos/transporte"', false)
            ->assertDontSee('utm_medium', false);
    }

    public function test_course_api_exposes_only_configured_seo_fields(): void
    {
        Course::factory()->create(['slug' => 'seo', 'meta_title' => 'Título público', 'meta_description' => 'Descrição pública']);

        $this->getJson('/api/courses/seo')->assertJsonPath('data.meta_title', 'Título público')
            ->assertJsonPath('data.meta_description', 'Descrição pública')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_course_falls_back_to_name_and_summary_and_escapes_attributes(): void
    {
        $this->prepareSite();
        Course::factory()->create(['slug' => 'fallback', 'name' => 'Curso <especial>', 'summary' => 'Resumo "><script>alert(1)</script>', 'meta_title' => '', 'meta_description' => '', 'cover_image_path' => null]);

        $this->get('https://especializa.example.com/cursos/fallback')
            ->assertSee('<title>Curso &lt;especial&gt; | Especializa Condutor</title>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Resumo', false);
    }

    public static function missingPages(): array
    {
        return [['/nao-existe'], ['/cursos/slug-invalido'], ['/cursos/oculto']];
    }

    #[DataProvider('missingPages')]
    public function test_unavailable_pages_have_real_404_and_no_indexable_metadata(string $path): void
    {
        $this->prepareSite();
        Course::factory()->create(['slug' => 'oculto', 'name' => 'Não divulgar', 'meta_title' => 'SEO privado', 'is_published' => false]);

        $this->get('https://especializa.example.com'.$path)->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Página não encontrada', false)
            ->assertDontSee('SEO privado', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('property="og:image"', false);
    }

    public function test_sitemap_contains_only_published_courses_and_valid_xml(): void
    {
        $this->prepareSite();
        Course::factory()->create(['slug' => 'publicado']);
        Course::factory()->create(['slug' => 'rascunho', 'is_published' => false]);

        $response = $this->get('https://especializa.example.com/sitemap.xml');

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://especializa.example.com/</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/cursos</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/quem-somos</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/politica-de-privacidade</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/termos-de-uso</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/cursos/publicado</loc>', false)
            ->assertDontSee('rascunho', false);
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_sitemap_immediately_removes_unpublished_courses(): void
    {
        $this->prepareSite();
        $course = Course::factory()->create(['slug' => 'antes-publicado']);
        $course->update(['is_published' => false]);

        $this->get('https://especializa.example.com/sitemap.xml')->assertDontSee('antes-publicado', false);
    }

    public function test_large_catalog_has_paginated_sitemaps(): void
    {
        $this->prepareSite();
        Course::factory()->count(1001)->create(['category_id' => null]);

        $this->get('https://especializa.example.com/sitemap.xml')
            ->assertSee('<sitemapindex', false)
            ->assertSee('sitemap.xml?page=2', false);
    }

    public function test_sitemap_second_page_does_not_repeat_first_page_courses(): void
    {
        $this->prepareSite();
        Course::factory()->count(1000)->create(['category_id' => null]);
        Course::factory()->create(['slug' => 'ultima-pagina', 'category_id' => null]);

        $this->get('https://especializa.example.com/sitemap.xml?page=2')
            ->assertSee('cursos/ultima-pagina', false)
            ->assertDontSee('<loc>https://especializa.example.com/</loc>', false);
    }

    public function test_production_robots_lists_sitemap_and_blocks_admin_and_api(): void
    {
        $this->prepareSite();

        $this->get('https://especializa.example.com/robots.txt')
            ->assertSee("Allow: /\nDisallow: /admin\nDisallow: /api", false)
            ->assertSee('Sitemap: https://especializa.example.com/sitemap.xml', false);
    }

    public static function unsafeOrigins(): array
    {
        return [[null], ['http://localhost:5173'], ['https://localhost'], ['https://127.0.0.1'], ['https://preview.test'], ['https://especializa.example.com/?utm_source=x']];
    }

    #[DataProvider('unsafeOrigins')]
    public function test_invalid_domain_cannot_enable_indexing(?string $origin): void
    {
        $this->prepareSite();
        config(['site.url' => $origin]);

        $this->get('https://especializa.example.com/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('rel="canonical"', false);
    }

    public function test_localhost_stays_blocked_even_with_production_configuration(): void
    {
        $this->prepareSite();

        $this->get('http://localhost/robots.txt')->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:', false);
    }

    public function test_disabled_environment_has_no_sitemap(): void
    {
        $this->prepareSite();
        config(['site.indexable' => false]);

        $this->get('https://especializa.example.com/sitemap.xml')->assertServiceUnavailable()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_html_is_never_indexable(): void
    {
        $this->prepareSite();

        $this->get('https://especializa.example.com/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('rel="canonical"', false);
    }

    public function test_missing_build_returns_safe_unavailable_response(): void
    {
        $this->prepareSite();
        config(['site.frontend_build_path' => storage_path('missing-build')]);

        $this->get('/')->assertServiceUnavailable()->assertSee('Site temporariamente indisponível')->assertDontSee('missing-build');
    }

    public function test_assets_are_served_but_source_and_private_files_are_not(): void
    {
        $this->prepareSite();
        Storage::disk('local')->put('site/assets/app.js', 'export const ready = true;');

        $this->get('/assets/app.js')->assertHeader('Content-Type', 'text/javascript; charset=utf-8')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_private_files_are_not_served_as_assets(): void
    {
        $this->prepareSite();
        Storage::disk('local')->put('site/assets/secret.php', '<?php secret();');

        $this->get('/assets/secret.php')->assertNotFound()->assertDontSee('secret();', false);
    }
}
