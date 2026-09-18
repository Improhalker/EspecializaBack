<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MediaLibraryService
{
    public function __construct(private ImageProcessingService $processor, private MediaStorageService $storage) {}

    /** @param array<string, mixed> $data */
    public function store(UploadedFile $file, array $data): Media
    {
        abort_unless($this->storage->configured(), 503, 'Armazenamento de mídia não configurado. Configure as credenciais do Supabase no Laravel.');
        $uploadKey = $data['upload_key'] ?? (string) Str::uuid();
        $existing = Media::query()->where('upload_key', $uploadKey)->first();
        if ($existing) {
            abort_unless($existing->status === 'ready', 409,
                'Este envio está pendente. Atualize a biblioteca antes de tentar novamente.');

            return $existing;
        }

        $processed = $this->processor->process($file);
        $content = $processed['content'];
        unset($processed['content']);
        $uuid = (string) Str::uuid();
        $originalName = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, '');
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'imagem';
        $storedName = $uuid.'-'.Str::limit($slug, 120, '').'.'.$processed['extension'];
        $decorative = (bool) ($data['is_decorative'] ?? false);
        $media = Media::query()->createOrFirst(['upload_key' => $uploadKey], [
            ...$processed, 'uuid' => $uuid, 'upload_key' => $uploadKey,
            'original_name' => $originalName, 'stored_name' => $storedName,
            'path' => 'courses/'.now()->format('Y/m').'/'.$storedName,
            'bucket' => config('media.bucket'), 'disk' => 'supabase', 'status' => 'uploading',
            'visibility' => 'public', 'is_decorative' => $decorative,
            'alt_is_custom' => filled($data['alt_text'] ?? null),
            'alt_text' => $decorative ? null : ($data['alt_text'] ?? MediaAltText::suggest($data['course_name'] ?? null, $originalName)),
        ]);

        if (! $media->wasRecentlyCreated) {
            abort_unless($media->status === 'ready', 409, 'Este envio está em processamento. Atualize a biblioteca.');

            return $media;
        }

        try {
            $this->storage->put($media, $content);
            $media->update(['status' => 'ready']);
        } catch (Throwable $exception) {
            $media->update(['status' => 'failed']);
            try {
                $this->storage->delete($media);
                $media->delete();
            } catch (Throwable) {
                Log::warning('Mídia aguarda limpeza após falha de envio.', ['media_id' => $media->id]);
            }
            throw $exception;
        }

        return $media->refresh();
    }

    public function delete(Media $media): void
    {
        $media = DB::transaction(function () use ($media): Media {
            $locked = Media::query()->lockForUpdate()->findOrFail($media->id);
            $count = $locked->courses()->count();
            if ($count > 0) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Esta mídia está em uso. Troque ou remova a capa dos cursos antes de excluí-la.',
                    'usage_count' => $count,
                    'usages' => $locked->courses()->select('id', 'name', 'slug')->orderBy('id')->limit(20)->get(),
                ], 409));
            }
            abort_if($locked->status === 'uploading' && $locked->updated_at->gt(now()->subMinutes(10)), 409, 'Aguarde o envio terminar antes de excluir.');
            $locked->update(['status' => 'deleting']);

            return $locked;
        });

        $this->storage->delete($media);
        $media->delete();
    }
}
