<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPublicSiteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function prepareSite(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('site/index.html', '<!doctype html><html lang="pt-BR"><head><!-- public-seo:start --><title>Default</title><!-- public-seo:end --></head><body><div id="app"></div></body></html>');
        config(['site.url' => 'https://especializa.example.com', 'site.indexable' => true, 'site.frontend_build_path' => Storage::disk('local')->path('site')]);
    }

    public function test_article_html_contains_seo_title_and_main_content(): void
    {
        $this->prepareSite();
        BlogPost::factory()->published('2026-11-20 13:00:00')->create([
            'slug' => 'renovar-mopp', 'title' => 'Como renovar o MOPP', 'excerpt' => 'Prazos & documentos.',
            'meta_title' => 'Renovação do MOPP: guia rápido', 'meta_description' => 'Saiba quando renovar.',
            'body' => '<h2>Prazos</h2><p>O curso vale por cinco anos.</p>',
        ]);

        $this->get('https://especializa.example.com/blog/renovar-mopp?utm_source=qa')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'index, follow')
            ->assertSee('<title>Renovação do MOPP: guia rápido | Especializa Condutor</title>', false)
            ->assertSee('name="description" content="Saiba quando renovar."', false)
            ->assertSee('rel="canonical" href="https://especializa.example.com/blog/renovar-mopp"', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="article:published_time"', false)
            ->assertSee('<h1>Como renovar o MOPP</h1>', false)
            ->assertSee('20 de novembro de 2026', false)
            ->assertSee('Prazos &amp; documentos.', false)
            ->assertSee('<h2>Prazos</h2><p>O curso vale por cinco anos.</p>', false)
            ->assertDontSee('utm_source', false);
    }

    public function test_listing_html_contains_published_articles_only(): void
    {
        $this->prepareSite();
        BlogPost::factory()->published()->create(['slug' => 'publicado', 'title' => 'Artigo publicado']);
        BlogPost::factory()->create(['slug' => 'rascunho', 'title' => 'Rascunho secreto']);

        $this->get('https://especializa.example.com/blog')
            ->assertOk()
            ->assertSee('<title>Blog | Especializa Condutor</title>', false)
            ->assertSee('rel="canonical" href="https://especializa.example.com/blog"', false)
            ->assertSee('href="/blog/publicado"', false)
            ->assertDontSee('Rascunho secreto', false);
    }

    public function test_missing_draft_and_unpublished_articles_return_real_404(): void
    {
        $this->prepareSite();
        BlogPost::factory()->create(['slug' => 'rascunho', 'meta_title' => 'SEO privado']);
        BlogPost::factory()->unpublished()->create(['slug' => 'fora-do-ar', 'title' => 'Artigo retirado']);

        foreach (['/blog/nao-existe', '/blog/rascunho', '/blog/fora-do-ar'] as $path) {
            $this->get('https://especializa.example.com'.$path)->assertNotFound()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertDontSee('SEO privado', false)
                ->assertDontSee('Artigo retirado', false)
                ->assertDontSee('rel="canonical"', false);
        }
    }

    public function test_sitemap_lists_blog_and_only_published_articles(): void
    {
        $this->prepareSite();
        BlogPost::factory()->published()->create(['slug' => 'publicado']);
        BlogPost::factory()->create(['slug' => 'rascunho']);
        BlogPost::factory()->unpublished()->create(['slug' => 'retirado']);

        $response = $this->get('https://especializa.example.com/sitemap.xml')
            ->assertSee('<loc>https://especializa.example.com/blog</loc>', false)
            ->assertSee('<loc>https://especializa.example.com/blog/publicado</loc>', false)
            ->assertDontSee('rascunho', false)
            ->assertDontSee('retirado', false);
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }
}
