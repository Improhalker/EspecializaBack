<?php

return [
    // Enable only after the public domain points to Laravel's HTML routes.
    'url' => env('SITE_URL'),
    'indexable' => (bool) env('SITE_INDEXABLE', false),
    'frontend_build_path' => env('FRONTEND_BUILD_PATH', base_path('../especializaCondutor2026/dist')),
    'public_cache_store' => env('PUBLIC_CACHE_STORE', 'file'),
    'course_cache_seconds' => (int) env('PUBLIC_COURSE_CACHE_SECONDS', 900),
];
