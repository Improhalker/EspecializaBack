<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCoursesRequest;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCoursePublicationRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\AdminCourseResource;
use App\Models\Course;
use App\Models\Media;
use App\Services\MediaAltText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(ListCoursesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $courses = Course::query()
            ->with(['coverMedia', 'category', 'modalities' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('slug', "%{$search}%");
                });
            })
            ->when($validated['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('is_published', $status === 'published'))
            ->when(array_key_exists('featured', $validated), fn ($query) => $query->where('is_featured', $request->boolean('featured')))
            ->when($validated['modality'] ?? null, fn ($query, string $modality) => $query->whereHas('modalities', fn ($query) => $query->where('name', $modality)))
            ->orderBy($validated['sort'] ?? 'updated_at', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return AdminCourseResource::collection($courses);
    }

    public function store(StoreCourseRequest $request): AdminCourseResource
    {
        $course = DB::transaction(function () use ($request): Course {
            $data = $request->validated();
            $course = Course::query()->create($this->courseAttributes($data));
            $this->syncRelations($course, $data);

            return $course;
        });

        return new AdminCourseResource($course->load($this->relations()));
    }

    public function show(Course $course): AdminCourseResource
    {
        return new AdminCourseResource($course->load($this->relations()));
    }

    public function update(UpdateCourseRequest $request, Course $course): AdminCourseResource
    {
        DB::transaction(function () use ($request, $course): void {
            $data = $request->validated();
            $course->update($this->courseAttributes($data));
            $this->syncRelations($course, $data);
        });

        return new AdminCourseResource($course->fresh()->load($this->relations()));
    }

    public function updatePublication(UpdateCoursePublicationRequest $request, Course $course): AdminCourseResource
    {
        $course->update($request->validated());

        return new AdminCourseResource($course->load($this->relations()));
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function courseAttributes(array $data): array
    {
        if (isset($data['cover_media_id'])) {
            $media = Media::query()->lockForUpdate()->find($data['cover_media_id']);
            if (! $media || $media->status !== 'ready' || $media->visibility !== 'public') {
                throw ValidationException::withMessages(['cover_media_id' => 'Esta imagem não está disponível. Selecione outra na biblioteca.']);
            }
            if ($media->is_decorative || blank($media->alt_text)) {
                $media->update(['is_decorative' => false, 'alt_text' => MediaAltText::suggest($data['name'])]);
            }
            $data['cover_image_path'] = null;
        }

        return collect($data)->only([
            'category_id',
            'name',
            'short_name',
            'slug',
            'summary',
            'description',
            'requirements',
            'cover_image_path',
            'cover_media_id',
            'cover_alt_text',
            'meta_title',
            'meta_description',
            'is_featured',
            'is_published',
            'sort_order',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Course $course, array $data): void
    {
        if (array_key_exists('modalities', $data)) {
            $existingModalities = $course->modalities()->get()->keyBy('id');
            $receivedModalityIds = [];

            foreach ($data['modalities'] ?? [] as $modalityData) {
                $modalityId = $modalityData['id'] ?? null;
                $attributes = collect($modalityData)->except('id')->all();

                if ($modalityId && $existingModalities->has($modalityId)) {
                    $existingModalities->get($modalityId)->update($attributes);
                    $receivedModalityIds[] = $modalityId;

                    continue;
                }

                $created = $course->modalities()->create($attributes);
                $receivedModalityIds[] = $created->id;
            }

            $course->modalities()->whereNotIn('id', $receivedModalityIds)->delete();
        }

        if (array_key_exists('faqs', $data)) {
            $existingFaqs = $course->faqs()->get()->keyBy('id');
            $receivedFaqIds = [];

            foreach ($data['faqs'] ?? [] as $faqData) {
                $faqId = $faqData['id'] ?? null;
                $attributes = collect($faqData)->except('id')->all();

                if ($faqId && $existingFaqs->has($faqId)) {
                    $existingFaqs->get($faqId)->update($attributes);
                    $receivedFaqIds[] = $faqId;

                    continue;
                }

                $created = $course->faqs()->create($attributes);
                $receivedFaqIds[] = $created->id;
            }

            $course->faqs()->whereNotIn('id', $receivedFaqIds)->delete();
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'coverMedia',
            'category',
            'modalities' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'faqs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ];
    }
}
