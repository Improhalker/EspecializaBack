<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTestimonialsRequest;
use App\Http\Requests\StoreTestimonialRequest;
use App\Http\Requests\UpdateTestimonialPublicationRequest;
use App\Http\Requests\UpdateTestimonialRequest;
use App\Http\Resources\AdminTestimonialResource;
use App\Models\Media;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class TestimonialController extends Controller
{
    public function index(ListTestimonialsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $testimonials = Testimonial::query()
            ->with('avatarMedia')
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('content', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('is_published', $status === 'published'))
            ->orderBy($validated['sort'] ?? 'sort_order', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return AdminTestimonialResource::collection($testimonials);
    }

    public function store(StoreTestimonialRequest $request): AdminTestimonialResource
    {
        $testimonial = Testimonial::query()->create([
            'is_published' => false,
            'sort_order' => 0,
            ...$this->testimonialAttributes($request->validated()),
        ]);

        return new AdminTestimonialResource($testimonial->load('avatarMedia'));
    }

    public function show(Testimonial $testimonial): AdminTestimonialResource
    {
        return new AdminTestimonialResource($testimonial->load('avatarMedia'));
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): AdminTestimonialResource
    {
        $testimonial->update($this->testimonialAttributes($request->validated()));

        return new AdminTestimonialResource($testimonial->fresh()->load('avatarMedia'));
    }

    public function updatePublication(UpdateTestimonialPublicationRequest $request, Testimonial $testimonial): AdminTestimonialResource
    {
        $testimonial->update($request->validated());

        return new AdminTestimonialResource($testimonial->load('avatarMedia'));
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function testimonialAttributes(array $data): array
    {
        if (isset($data['avatar_media_id'])) {
            $media = Media::query()->lockForUpdate()->find($data['avatar_media_id']);
            if (! $media || $media->status !== 'ready' || $media->visibility !== 'public') {
                throw ValidationException::withMessages(['avatar_media_id' => 'Esta imagem não está disponível. Selecione outra na biblioteca.']);
            }
        }

        return collect($data)->only([
            'name', 'content', 'rating', 'avatar_media_id', 'course_id', 'is_published', 'sort_order',
        ])->all();
    }
}
