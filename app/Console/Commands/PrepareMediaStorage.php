<?php

namespace App\Console\Commands;

use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PrepareMediaStorage extends Command
{
    protected $signature = 'media:prepare';

    protected $description = 'Cria ou verifica o bucket privado da biblioteca de mídia no Supabase';

    public function handle(MediaStorageService $storage): int
    {
        try {
            $storage->prepareBucket();
            $this->info('Bucket de mídia pronto. Upload e entrega ocorrem exclusivamente pela API Laravel.');

            return self::SUCCESS;
        } catch (HttpExceptionInterface $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
