<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Cache;

class MediaDeliveryCache
{
    public function get(string $uuid): ?string
    {
        return Cache::store(config('site.public_cache_store'))->get($this->key($uuid));
    }

    public function put(Media $media, string $content): void
    {
        Cache::store(config('site.public_cache_store'))->put($this->key($media->uuid), $content, config('media.server_cache_seconds'));
    }

    public function forget(Media $media): void
    {
        Cache::store(config('site.public_cache_store'))->forget($this->key($media->uuid));
    }

    private function key(string $uuid): string
    {
        return 'public-media:'.$uuid;
    }
}
