<?php

return [
    // Enable only after the public domain points to Laravel's HTML routes.
    'url' => env('SITE_URL'),
    'indexable' => (bool) env('SITE_INDEXABLE', false),
    'frontend_build_path' => env('FRONTEND_BUILD_PATH', base_path('../especializaCondutor2026/dist')),
    'public_cache_store' => env('PUBLIC_CACHE_STORE', 'file'),
    'course_cache_seconds' => (int) env('PUBLIC_COURSE_CACHE_SECONDS', 900),
    // Browser-facing Cache-Control max-age for /api/home, /api/courses and /api/courses/{slug}.
    // Kept short so admin edits become visible to already-cached browsers quickly; the
    // server-side PublicCourseCache revision bump makes new visitors see changes instantly.
    'public_response_cache_seconds' => (int) env('PUBLIC_RESPONSE_CACHE_SECONDS', 60),
];
