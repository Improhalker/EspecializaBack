<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\PublicCourseCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private PublicCourseCache $publicCache) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->withCount('courses')->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        return new CategoryResource(Category::query()->create($request->validated()));
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('courses'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());
        $this->publicCache->invalidate();

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        $this->publicCache->invalidate();

        return response()->json([], 204);
    }
}
