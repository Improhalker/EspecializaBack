<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListMediaRequest;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaLibraryService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    public function index(ListMediaRequest $request, MediaStorageService $storage): AnonymousResourceCollection
    {
        $data = $request->validated();
        $media = Media::query()->withCount($this->usageRelations())
            ->when($data['search'] ?? null, function ($query, string $search): void {
                $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $query->whereRaw("original_name {$operator} ? ESCAPE ?", ['%'.$literal.'%', '\\']);
            })
            ->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')
            ->orderBy('id', $data['direction'] ?? 'desc')
            ->paginate($data['per_page'] ?? 24)->withQueryString();

        return MediaResource::collection($media)->additional(['upload' => [
            'max_size_bytes' => config('media.max_upload_kb') * 1024,
            'max_svg_bytes' => config('media.max_svg_kb') * 1024,
            'max_dimension' => config('media.max_dimension'),
            'accepted_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'storage_configured' => $storage->configured(),
            'processing_available' => extension_loaded('gd') && function_exists('imagewebp'),
        ]]);
    }

    public function store(StoreMediaRequest $request, MediaLibraryService $library): MediaResource
    {
        return new MediaResource($library->store($request->file('file'), $request->validated())->loadCount($this->usageRelations()));
    }

    public function show(Media $media): MediaResource
    {
        return new MediaResource($media->loadCount($this->usageRelations()));
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $media = DB::transaction(function () use ($request, $media): Media {
            $locked = Media::query()->lockForUpdate()->findOrFail($media->id);
            abort_unless($locked->status === 'ready', 409, 'Esta mídia não está disponível para edição.');
            abort_if($request->boolean('is_decorative') && $locked->courses()->exists(), 409,
                'Uma capa de curso precisa de texto alternativo e não pode ser decorativa.');
            $locked->update([
                'alt_text' => $request->boolean('is_decorative') ? null : $request->validated('alt_text'),
                'alt_is_custom' => ! $request->boolean('is_decorative'),
                'is_decorative' => $request->boolean('is_decorative'),
            ]);

            return $locked;
        });

        return new MediaResource($media->loadCount($this->usageRelations()));
    }

    public function usages(Media $media, MediaLibraryService $library): JsonResponse
    {
        return response()->json($library->usages($media));
    }

    public function destroy(Media $media, MediaLibraryService $library): JsonResponse
    {
        $library->delete($media);

        return response()->json([], 204);
    }

    /** @return array<int, string> */
    private function usageRelations(): array
    {
        return ['courses', 'heroCourses', 'mobileHeroCourses', 'pageHeroes', 'mobilePageHeroes', 'testimonialAvatars'];
    }
}
