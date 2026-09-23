<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaDeliveryCache;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class WarmPublicMediaCache extends Command
{
    protected $signature = 'media:warm-cache';

    protected $description = 'Aquece o cache local das imagens públicas prontas para entrega.';

    public function handle(MediaStorageService $storage, MediaDeliveryCache $cache): int
    {
        $warmed = 0;
        $failed = 0;

        Media::query()->where('status', 'ready')->where('visibility', 'public')
            ->chunkById(50, function (Collection $items) use ($storage, $cache, &$warmed, &$failed): void {
                foreach ($items as $media) {
                    if ($cache->get($media->uuid) !== null) {
                        continue;
                    }

                    try {
                        $cache->put($media, $storage->get($media));
                        $warmed++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->components->warn("Falha ao aquecer a mídia {$media->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->components->info("Cache aquecido para {$warmed} imagem(ns). Falhas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
