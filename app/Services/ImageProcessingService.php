<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Throwable;

class ImageProcessingService
{
    public function __construct(private SvgSanitizer $svgSanitizer) {}

    /** @return array<string, mixed> */
    public function process(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        if (! isset($expected[$extension]) || $mime !== $expected[$extension]) {
            throw ValidationException::withMessages(['file' => 'O conteúdo da imagem não corresponde à extensão do arquivo.']);
        }

        if ($extension === 'svg') {
            $result = $this->svgSanitizer->process($file->getContent());
            $result['extension'] = 'svg';
            $result['mime_type'] = 'image/svg+xml';
        } else {
            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions || config('media.max_pixels') < $dimensions[0] * $dimensions[1]) {
                throw ValidationException::withMessages(['file' => 'Imagem inválida ou com resolução excessiva. Reduza as dimensões e tente novamente.']);
            }
            abort_unless(extension_loaded('gd') && function_exists('imagewebp'), 503,
                'Processamento de imagens indisponível. Habilite GD com suporte a WebP no PHP do servidor.');
            try {
                $image = ImageManager::gd(autoOrientation: true, decodeAnimation: false, strip: true)
                    ->read($file->getRealPath());
                $originalWidth = $image->width();
                $originalHeight = $image->height();
                $image->scaleDown(width: config('media.max_dimension'), height: config('media.max_dimension'));
                $result = [
                    'content' => (string) $image->toWebp(quality: max(1, min(100, config('media.webp_quality')))),
                    'width' => $image->width(), 'height' => $image->height(),
                    'original_width' => $originalWidth, 'original_height' => $originalHeight,
                    'extension' => 'webp', 'mime_type' => 'image/webp',
                ];
            } catch (Throwable) {
                throw ValidationException::withMessages(['file' => 'Não foi possível processar esta imagem. Verifique se o arquivo está íntegro.']);
            }
        }

        $originalSize = $file->getSize();
        $size = strlen($result['content']);
        if ($size > config('media.max_upload_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'A imagem processada excedeu o limite permitido. Reduza as dimensões.']);
        }

        return [...$result, 'original_extension' => $extension, 'original_mime_type' => $mime,
            'original_size' => $originalSize, 'size' => $size,
            'reduction_percent' => round((1 - $size / max(1, $originalSize)) * 100, 2)];
    }
}
