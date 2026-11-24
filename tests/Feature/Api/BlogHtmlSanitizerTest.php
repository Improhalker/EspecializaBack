<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Services\BlogHtmlSanitizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlogHtmlSanitizerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function sanitize(string $html): array
    {
        return app(BlogHtmlSanitizer::class)->sanitize($html);
    }

    public function test_preserves_editorial_formatting_and_accents(): void
    {
        $html = '<h2 id="requisitos">Requisitos</h2><p>Atenção: <strong>CNH</strong> e <em>exame</em> em dia.</p>'
            .'<ul><li>Item</li></ul><ol start="3"><li>Três</li></ol><blockquote><p>Citação</p></blockquote>'
            .'<table><thead><tr><th scope="col">Curso</th></tr></thead><tbody><tr><td colspan="2">MOPP</td></tr></tbody></table>'
            .'<p><a href="/cursos/mopp">Conheça</a> <a href="mailto:contato@example.com">e-mail</a></p>';

        $result = $this->sanitize($html);

        $this->assertSame($html, $result['html']);
        $this->assertSame([], $result['removed']);
    }

    public function test_removes_scripts_event_handlers_styles_and_embeds(): void
    {
        $result = $this->sanitize('<p onclick="steal()" style="color:red">Texto</p><script>alert(1)</script>'
            .'<iframe src="https://example.com"></iframe><object data="x"></object><svg><script>alert(1)</script></svg>'
            .'<form action="/x"><input name="a"></form><style>p{}</style><!-- comentário -->');

        $this->assertSame('<p>Texto</p>', $result['html']);
        $this->assertContains('<script>', $result['removed']);
        $this->assertContains('atributo de evento (onclick)', $result['removed']);
        $this->assertContains('<iframe>', $result['removed']);
    }

    public function test_blocks_dangerous_urls_and_protects_external_links(): void
    {
        $result = $this->sanitize('<a href="javascript:alert(1)">a</a><a href=" jav&#x09;ascript:alert(1)">b</a>'
            .'<a href="data:text/html;base64,PHNjcmlwdD4=">c</a><a href="//evil.example">d</a><a href="vbscript:x">e</a>'
            .'<a href="https://www.gov.br/transito" target="_top">f</a>');

        $this->assertSame('<a>a</a><a>b</a><a>c</a><a>d</a><a>e</a>'
            .'<a href="https://www.gov.br/transito" target="_blank" rel="noopener noreferrer">f</a>', $result['html']);
        $this->assertContains('link com endereço não permitido', $result['removed']);
    }

    public function test_converts_h1_to_h2_and_unwraps_unknown_elements(): void
    {
        $result = $this->sanitize('<h1>Título</h1><section><span class="x">Conteúdo</span> <font color="red">livre</font></section>');

        $this->assertSame('<h2>Título</h2>Conteúdo livre', $result['html']);
    }

    public function test_only_library_images_are_kept_with_alt_text_and_dimensions(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $media = Media::factory()->create(['width' => 1200, 'height' => 630]);
        $pending = Media::factory()->create(['status' => 'uploading']);

        $result = $this->sanitize('<figure><img src="http://localhost:8000/api/media/'.$media->uuid.'" alt="Caminhão na estrada" onerror="x()"><figcaption>Legenda</figcaption></figure>'
            .'<img src="https://tracker.example/pixel.png" alt="">'
            .'<img src="/api/media/'.$pending->uuid.'" alt="Pendente">'
            .'<img src="https://evil.example/api/media/'.$media->uuid.'" alt="Outro host">');

        $this->assertStringContainsString('<img src="'.$media->deliveryUrl().'" alt="Caminhão na estrada" width="1200" height="630" loading="lazy" decoding="async">', $result['html']);
        $this->assertStringContainsString('<figcaption>Legenda</figcaption>', $result['html']);
        $this->assertStringNotContainsString('tracker.example', $result['html']);
        $this->assertStringNotContainsString('Pendente', $result['html']);
        $this->assertStringNotContainsString('evil.example', $result['html']);
        $this->assertSame([$media->id], $result['media_ids']);
        $this->assertContains('imagem fora da biblioteca de mídia', $result['removed']);
    }

    public function test_sanitizing_a_saved_body_again_changes_nothing(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $media = Media::factory()->create();
        $first = $this->sanitize('<p><a href="https://www.gov.br" rel="opener">gov</a></p><img src="/api/media/'.$media->uuid.'" alt="Foto">');

        $second = $this->sanitize($first['html']);

        $this->assertSame($first['html'], $second['html']);
        $this->assertSame([], $second['removed']);
        $this->assertStringContainsString('rel="noopener noreferrer"', $second['html']);
    }
}
