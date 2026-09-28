<?php

return [
    'supabase_url' => env('SUPABASE_URL'),
    'supabase_key' => env('SUPABASE_SECRET_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY'),
    'bucket' => env('SUPABASE_MEDIA_BUCKET', 'media'),
    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 10240),
    'max_dimension' => (int) env('MEDIA_MAX_DIMENSION', 2400),
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 24000000),
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    'max_svg_kb' => (int) env('MEDIA_MAX_SVG_KB', 512),
    // Browser-facing Cache-Control max-age for /api/media/{uuid}. Media bytes are immutable
    // once ready (a re-upload always gets a new UUID), so this matches the 7-day server-side
    // TTL below rather than the old 5-minute default that forced needless re-downloads.
    'delivery_cache_seconds' => (int) env('MEDIA_CACHE_SECONDS', 604800),
    'server_cache_seconds' => (int) env('PUBLIC_MEDIA_CACHE_SECONDS', 604800),
];
