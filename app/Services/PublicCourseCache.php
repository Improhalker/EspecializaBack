<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicCourseCache
{
    public function key(string $slug): string
    {
        $revision = Cache::store(config('site.public_cache_store'))->get('public-courses:revision', 'initial');

        return 'public-courses:'.$revision.':'.hash('sha256', $slug);
    }

    public function get(string $key): ?string
    {
        return Cache::store(config('site.public_cache_store'))->get($key);
    }

    public function put(string $key, string $json): void
    {
        Cache::store(config('site.public_cache_store'))->put($key, $json, config('site.course_cache_seconds'));
    }

    public function invalidate(): void
    {
        Cache::store(config('site.public_cache_store'))->forever('public-courses:revision', (string) Str::uuid());
    }
}
