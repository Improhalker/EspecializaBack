<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSharedFaqsRequest;
use App\Http\Requests\StoreSharedFaqRequest;
use App\Http\Requests\UpdateSharedFaqPublicationRequest;
use App\Http\Requests\UpdateSharedFaqRequest;
use App\Http\Resources\AdminSharedFaqResource;
use App\Models\SharedFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SharedFaqController extends Controller
{
    public function index(ListSharedFaqsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $faqs = SharedFaq::query()
            ->withCount('courses')
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('question', "%{$search}%")
                        ->orWhereLike('answer', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('is_published', $status === 'published'))
            ->when($validated['application_mode'] ?? null, fn ($query, string $mode) => $query->where('application_mode', $mode))
            ->when($validated['course_id'] ?? null, fn ($query, int $courseId) => $query->applicableTo($courseId))
            ->orderBy($validated['sort'] ?? 'sort_order', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return AdminSharedFaqResource::collection($faqs);
    }

    public function store(StoreSharedFaqRequest $request): AdminSharedFaqResource
    {
        $faq = DB::transaction(function () use ($request): SharedFaq {
            $data = $request->validated();
            $faq = SharedFaq::query()->create([
                'is_published' => false,
                'sort_order' => 0,
                ...$this->faqAttributes($data),
            ]);
            $faq->courses()->sync($this->courseIds($data));

            return $faq;
        });

        return new AdminSharedFaqResource($faq->load('courses')->loadCount('courses'));
    }

    public function show(SharedFaq $faq): AdminSharedFaqResource
    {
        return new AdminSharedFaqResource($faq->load('courses')->loadCount('courses'));
    }

    public function update(UpdateSharedFaqRequest $request, SharedFaq $faq): AdminSharedFaqResource
    {
        DB::transaction(function () use ($request, $faq): void {
            $data = $request->validated();
            $faq->update($this->faqAttributes($data));
            $faq->courses()->sync($this->courseIds($data));
        });

        return new AdminSharedFaqResource($faq->fresh()->load('courses')->loadCount('courses'));
    }

    public function updatePublication(UpdateSharedFaqPublicationRequest $request, SharedFaq $faq): AdminSharedFaqResource
    {
        $faq->update($request->validated());

        return new AdminSharedFaqResource($faq->load('courses')->loadCount('courses'));
    }

    public function destroy(SharedFaq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function faqAttributes(array $data): array
    {
        return collect($data)->only(['question', 'answer', 'application_mode', 'is_published', 'sort_order'])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function courseIds(array $data): array
    {
        return $data['application_mode'] === SharedFaq::APPLICATION_SELECTED_COURSES
            ? $data['course_ids']
            : [];
    }
}
