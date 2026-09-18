<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Http\Request;

class PublicSeo
{
    public function origin(): ?string
    {
        $url = rtrim(trim((string) config('site.url')), '/');
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')
            || preg_match('/(?:^|\.)(localhost|local|test|invalid)$/', $host)) {
            return null;
        }

        return $url;
    }

    public function indexable(Request $request): bool
    {
        return (bool) config('site.indexable') && $this->origin() !== null
            && rtrim($request->getSchemeAndHttpHost(), '/') === $this->origin();
    }

    /** @return array<string, mixed> */
    public function page(string $page, Request $request, ?Course $course = null): array
    {
        $title = match ($page) {
            'home' => 'Cursos para motoristas',
            'courses' => 'Cursos',
            'course' => trim((string) $course?->meta_title) ?: ($course?->name ?? 'Curso'),
            'not-found' => 'Página não encontrada',
            'error' => 'Não foi possível carregar a página',
            default => 'Administração',
        };
        $title = preg_replace('/(?:\s*[|—–-]\s*)?Especializa Condutor\s*$/iu', '', $title);
        $title = trim((string) $title) ?: 'Cursos';
        $description = match ($page) {
            'home' => 'Conheça os cursos do Especializa Condutor e fale com nossa equipe para esclarecer dúvidas e consultar condições.',
            'courses' => 'Explore o catálogo de cursos do Especializa Condutor. Consulte informações, modalidades e requisitos de cada curso.',
            'course' => trim((string) $course?->meta_description) ?: (trim((string) $course?->summary) ?: 'Conheça o curso '.$course?->name.' e consulte informações com a equipe do Especializa Condutor.'),
            'not-found' => 'Esta página não foi encontrada ou não está mais disponível. Acesse o início ou explore nossos cursos.',
            'error' => 'Não foi possível carregar as informações agora. Tente novamente em instantes.',
            default => 'Administração do Especializa Condutor.',
        };
        $path = match ($page) {
            'home' => '/',
            'courses' => '/cursos',
            'course' => '/cursos/'.rawurlencode((string) $course?->slug),
            default => null,
        };
        $origin = $this->origin();
        $cover = $course?->resolvedCover();
        $image = $cover['url'] ?? ($origin ? $origin.'/brand/especializa-condutor.png' : null);
        if ($image && str_starts_with($image, '/') && ! str_starts_with($image, '//')) {
            $image = $origin ? $origin.$image : null;
        }
        if ($image && ! preg_match('#^https?://#i', $image)) {
            $image = null;
        }

        return [
            'title' => $title.' | Especializa Condutor',
            'description' => $description,
            'canonical' => $origin && $path ? $origin.$path : null,
            'image' => $path ? $image : null,
            'image_alt' => $cover['alt_text'] ?? 'Especializa Condutor',
            'robots' => $path && $this->indexable($request) ? 'index, follow' : 'noindex, nofollow',
            'origin' => $origin,
            'indexable' => $this->indexable($request),
        ];
    }
}
