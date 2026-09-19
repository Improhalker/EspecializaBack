<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePageAppearanceRequest;
use App\Http\Resources\PageAppearanceResource;
use App\Models\Media;
use App\Models\PageAppearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PageAppearanceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $definitions = collect(config('page_appearances.pages', []))
            ->filter(fn (array $page): bool => $page['supports_hero'] ?? false)
            ->sortBy('sort_order');
        $saved = PageAppearance::query()->with(['heroMedia', 'heroMobileMedia'])
            ->whereIn('page_key', $definitions->keys())->get()->keyBy('page_key');
        $pages = $definitions->keys()->map(fn (string $key): PageAppearance => $saved->get($key)
            ?? new PageAppearance(['page_key' => $key]));

        return PageAppearanceResource::collection($pages);
    }

    public function show(string $pageKey): PageAppearanceResource
    {
        $this->ensureSupported($pageKey);
        $appearance = PageAppearance::query()->with(['heroMedia', 'heroMobileMedia'])
            ->where('page_key', $pageKey)->first() ?? new PageAppearance(['page_key' => $pageKey]);

        return new PageAppearanceResource($appearance);
    }

    public function update(UpdatePageAppearanceRequest $request, string $pageKey): JsonResponse
    {
        $this->ensureSupported($pageKey);
        $appearance = DB::transaction(function () use ($request, $pageKey): PageAppearance {
            $data = $request->validated();
            foreach (['hero_media_id', 'hero_mobile_media_id'] as $field) {
                if (! isset($data[$field])) {
                    continue;
                }

                $media = Media::query()->lockForUpdate()->find($data[$field]);
                if (! $media || $media->status !== 'ready' || $media->visibility !== 'public') {
                    throw ValidationException::withMessages([$field => 'Esta imagem não está disponível. Selecione outra na biblioteca.']);
                }
            }

            $appearance = PageAppearance::query()->firstOrCreate(['page_key' => $pageKey]);
            $appearance->update($data);

            return $appearance;
        });

        return (new PageAppearanceResource($appearance->load(['heroMedia', 'heroMobileMedia'])))
            ->response()->setStatusCode(200);
    }

    private function ensureSupported(string $pageKey): void
    {
        abort_unless(config('page_appearances.pages.'.$pageKey.'.supports_hero', false), 404);
    }
}
