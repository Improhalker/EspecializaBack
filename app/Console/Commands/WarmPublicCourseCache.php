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

    protected $description = 'Aquece o cache local das páginas de cursos publicados.';

    public function handle(Kernel $kernel, PublicCourseCache $cache): int
    {
        $cache->invalidate();
        $warmed = 0;
        $failed = 0;

        foreach (Course::query()->published()->select('slug')->cursor() as $course) {
            $url = rtrim(config('app.url'), '/').'/api/courses/'.rawurlencode($course->slug);
            $request = Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            if ($response->getStatusCode() === 200) {
                $warmed++;
            } else {
                $failed++;
                $this->components->warn("Falha ao aquecer o curso {$course->slug}: HTTP {$response->getStatusCode()}.");
            }
        }

        $this->components->info("Cache aquecido para {$warmed} curso(s). Falhas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
