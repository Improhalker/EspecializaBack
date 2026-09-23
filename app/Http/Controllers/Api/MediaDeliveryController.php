<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaDeliveryCache;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaDeliveryController extends Controller
{
    public function __invoke(Request $request, string $media, MediaStorageService $storage, MediaDeliveryCache $cache): Response
    {
        $started = hrtime(true);
        $databaseStarted = hrtime(true);
        $record = Media::query()->where('uuid', $media)->firstOrFail();
        $databaseMs = (hrtime(true) - $databaseStarted) / 1_000_000;
        abort_unless($record->status === 'ready' && $record->visibility === 'public', 404);
        $response = response('', 200, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => 'inline; filename="'.$record->uuid.'.'.$record->extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Cache-Control' => 'public, max-age='.config('media.delivery_cache_seconds'),
            'Timing-Allow-Origin' => '*',
        ]);
        $response->setEtag($record->uuid);
        if ($response->isNotModified($request)) {
            $response->headers->set('Server-Timing', sprintf('media-cache;desc="conditional", db;dur=%.1f, app;dur=%.1f',
                $databaseMs, (hrtime(true) - $started) / 1_000_000));

            return $response;
        }

        $cached = $cache->get($record->uuid);
        $hit = $cached !== null;
        $storageMs = 0.0;
        if ($hit) {
            $response->setContent($cached);
        } else {
            $storageStarted = hrtime(true);
            $content = $storage->get($record);
            $storageMs = (hrtime(true) - $storageStarted) / 1_000_000;
            $cache->put($record, $content);
            $response->setContent($content);
        }

        $response->headers->set('Server-Timing', sprintf('media-cache;desc="%s", db;dur=%.1f, storage;dur=%.1f, app;dur=%.1f',
            $hit ? 'hit' : 'miss', $databaseMs, $storageMs, (hrtime(true) - $started) / 1_000_000));

        return $response;
    }
}
