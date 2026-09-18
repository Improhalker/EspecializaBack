<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaStorageService
{
    public function configured(): bool
    {
        return filled(config('media.supabase_url')) && filled(config('media.supabase_key'));
    }

    public function put(Media $media, string $content): void
    {
        $response = $this->call(fn () => $this->client()
            ->withHeaders(['x-upsert' => 'false', 'Cache-Control' => 'max-age=31536000'])
            ->withBody($content, $media->mime_type)
            ->post('/object/'.$this->objectPath($media)));
        $this->ensureSuccess($response);
    }

    public function delete(Media $media): void
    {
        $response = $this->call(fn () => $this->client()->delete('/object/'.rawurlencode($media->bucket), [
            'prefixes' => [$media->path],
        ]));
        if (! $response->notFound() && (string) $response->json('statusCode') !== '404') {
            $this->ensureSuccess($response);
        }
    }

    public function get(Media $media): string
    {
        $response = $this->call(fn () => $this->client()->get('/object/authenticated/'.$this->objectPath($media)));
        abort_if($response->notFound() || (string) $response->json('statusCode') === '404', 404, 'Imagem não encontrada.');
        $this->ensureSuccess($response);

        return $response->body();
    }

    public function prepareBucket(): void
    {
        $bucket = config('media.bucket');
        $response = $this->call(fn () => $this->client()->get('/bucket/'.rawurlencode($bucket)));
        $missing = $response->notFound() || (string) $response->json('statusCode') === '404';
        if (! $missing) {
            $this->ensureSuccess($response);
            abort_if($response->json('public') !== false, 409,
                'O bucket existente é público. Use um bucket privado dedicado à biblioteca de mídia.');

            return;
        }

        $this->ensureSuccess($this->call(fn () => $this->client()->post('/bucket', [
            'id' => $bucket, 'name' => $bucket, 'public' => false,
            'file_size_limit' => config('media.max_upload_kb') * 1024,
            'allowed_mime_types' => ['image/webp', 'image/svg+xml'],
        ])));
    }

    private function client(): PendingRequest
    {
        abort_unless($this->configured(), 503,
            'Armazenamento de mídia não configurado. Configure as credenciais do Supabase no Laravel.');
        $key = config('media.supabase_key');
        $client = Http::baseUrl(rtrim(config('media.supabase_url'), '/').'/storage/v1')
            ->acceptJson()->withHeaders(['apikey' => $key])
            ->connectTimeout(5)->timeout(30)->withoutRedirecting();

        return str_starts_with($key, 'sb_secret_') ? $client : $client->withToken($key);
    }

    private function objectPath(Media $media): string
    {
        return rawurlencode($media->bucket).'/'.implode('/', array_map('rawurlencode', explode('/', $media->path)));
    }

    private function call(callable $operation): Response
    {
        try {
            return $operation();
        } catch (ConnectionException) {
            abort(503, 'Não foi possível conectar ao armazenamento. Tente novamente.');
        }
    }

    private function ensureSuccess(Response $response): void
    {
        if (! $response->successful()) {
            Log::warning('Falha no armazenamento de mídia.', ['status' => $response->status()]);
            abort(503, 'O armazenamento não concluiu a operação. Verifique a configuração do bucket e tente novamente.');
        }
    }
}
