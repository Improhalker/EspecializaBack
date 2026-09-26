<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\PublicSeo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicSiteController extends Controller
{
    public function __construct(private PublicSeo $seo) {}

    public function home(Request $request): Response
    {
        return $this->html('home', $request);
    }

    public function courses(Request $request): Response
    {
        return $this->html('courses', $request);
    }

    public function about(Request $request): Response
    {
        return $this->html('about', $request);
    }

    public function privacy(Request $request): Response
    {
        return $this->html('privacy', $request);
    }

    public function terms(Request $request): Response
    {
        return $this->html('terms', $request);
    }

    public function course(Request $request, string $slug): Response
    {
        try {
            $course = Course::query()->published()->with('coverMedia')->where('slug', $slug)->first();
        } catch (QueryException $exception) {
            report($exception);

            return $this->html('error', $request, status: 503);
        }

        return $course ? $this->html('course', $request, $course) : $this->html('not-found', $request, status: 404);
    }

    public function admin(Request $request): Response
    {
        return $this->html('admin', $request);
    }

    public function missing(Request $request): Response
    {
        abort_if($request->is('api', 'api/*'), 404);

        return $this->html('not-found', $request, status: 404);
    }

    public function asset(string $path): BinaryFileResponse
    {
        $root = realpath((string) config('site.frontend_build_path'));
        $file = $root ? realpath($root.DIRECTORY_SEPARATOR.$path) : false;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['js' => 'text/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'woff2' => 'font/woff2', 'ico' => 'image/x-icon'];
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file) && isset($types[$extension]), 404);

        return response()->file($file, [
            'Content-Type' => $types[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => str_starts_with($path, 'assets/') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
        ]);
    }

    private function html(string $page, Request $request, ?Course $course = null, int $status = 200): Response
    {
        $seo = $this->seo->page($page, $request, $course);
        $file = rtrim((string) config('site.frontend_build_path'), '/\\').'/index.html';
        if (! is_file($file)) {
            return response('<!doctype html><html lang="pt-BR"><head><meta name="robots" content="noindex, nofollow"><title>Site temporariamente indisponível</title></head><body><h1>Site temporariamente indisponível</h1><p>Tente novamente em instantes.</p></body></html>', 503, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
        }
        $html = file_get_contents($file);
        $head = view('seo.head', compact('seo'))->render();
        $html = preg_replace_callback('/<!-- public-seo:start -->.*?<!-- public-seo:end -->/s', fn (): string => $head, $html);
        $html = str_replace('<div id="app"></div>', '<div id="app"><noscript><h1>'.e($seo['title']).'</h1><p>'.e($seo['description']).'</p><a href="/">Início</a> · <a href="/cursos">Cursos</a></noscript></div>', $html);

        return response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => $seo['robots'],
            'Cache-Control' => 'no-store',
        ]);
    }
}
