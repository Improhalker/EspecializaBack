<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaDeliveryController extends Controller
{
    public function __invoke(Request $request, Media $media, MediaStorageService $storage): Response
    {
        abort_unless($media->status === 'ready' && $media->visibility === 'public', 404);
        $response = response('', 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$media->uuid.'.'.$media->extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Cache-Control' => 'public, max-age='.config('media.delivery_cache_seconds'),
        ]);
        $response->setEtag($media->uuid);
        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent($storage->get($media));

        return $response;
    }
}
