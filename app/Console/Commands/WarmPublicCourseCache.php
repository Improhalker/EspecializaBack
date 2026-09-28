<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\PublicCourseCache;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

class WarmPublicCourseCache extends Command
{
    protected $signature = 'courses:warm-cache';

    protected $description = 'Aquece o cache local da home, da listagem e das páginas de cursos publicados.';

    public function handle(Kernel $kernel, PublicCourseCache $cache): int
    {
        $cache->invalidate();
        $warmed = 0;
        $failed = 0;

        foreach (['/api/home', '/api/courses'] as $path) {
            if ($this->warm($kernel, $path)) {
                $warmed++;
            } else {
                $failed++;
                $this->components->warn("Falha ao aquecer {$path}.");
            }
        }

        foreach (Course::query()->published()->select('slug')->cursor() as $course) {
            if ($this->warm($kernel, '/api/courses/'.rawurlencode($course->slug))) {
                $warmed++;
            } else {
                $failed++;
                $this->components->warn("Falha ao aquecer o curso {$course->slug}.");
            }
        }

        $this->components->info("Cache aquecido para {$warmed} página(s). Falhas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function warm(Kernel $kernel, string $path): bool
    {
        $url = rtrim(config('app.url'), '/').$path;
        $request = Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response->getStatusCode() === 200;
    }
}
