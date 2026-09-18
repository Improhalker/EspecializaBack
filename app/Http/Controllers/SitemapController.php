<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\PublicSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private PublicSeo $seo) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->seo->indexable($request)) {
            return response('Sitemap indisponível neste ambiente.', 503, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
        }
        $origin = $this->seo->origin();
        $page = max(1, (int) $request->query('page', 1));
        $count = Course::query()->published()->count();
        $pages = max(1, (int) ceil($count / 1000));
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        if ($pages > 1 && ! $request->has('page')) {
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            for ($number = 1; $number <= $pages; $number++) {
                $xml .= '<sitemap><loc>'.$escape($origin.'/sitemap.xml?page='.$number).'</loc></sitemap>';
            }
            $xml .= '</sitemapindex>';
        } else {
            abort_if($page > $pages, 404);
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            if ($page === 1) {
                foreach (['/', '/cursos'] as $path) {
                    $xml .= '<url><loc>'.$escape($origin.$path).'</loc></url>';
                }
            }
            foreach (Course::query()->published()->orderBy('id')->forPage($page, 1000)->get(['slug', 'updated_at']) as $course) {
                $xml .= '<url><loc>'.$escape($origin.'/cursos/'.rawurlencode($course->slug)).'</loc>';
                if ($course->updated_at) {
                    $xml .= '<lastmod>'.$course->updated_at->toAtomString().'</lastmod>';
                }
                $xml .= '</url>';
            }
            $xml .= '</urlset>';
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function robots(Request $request): Response
    {
        $text = $this->seo->indexable($request)
            ? "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api\nSitemap: ".$this->seo->origin()."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
