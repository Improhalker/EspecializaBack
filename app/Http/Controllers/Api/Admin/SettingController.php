<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiteSettingsRequest;
use App\Models\Course;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const KEYS = ['contact', 'social_links', 'institutional', 'global_faq'];

    public function index(): JsonResponse
    {
        $settings = Setting::query()
            ->whereIn('key', self::KEYS)
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value]);

        return response()->json([
            'settings' => collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => $settings->get($key, [])]),
            'featured_course_ids' => Course::query()
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id'),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['settings'] ?? [] as $key => $value) {
                if (in_array($key, self::KEYS, true)) {
                    Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
                }
            }

            if (array_key_exists('featured_course_ids', $validated)) {
                $courseIds = $validated['featured_course_ids'];
                Course::query()->whereNotIn('id', $courseIds)->update(['is_featured' => false]);

                foreach ($courseIds as $index => $courseId) {
                    Course::query()->whereKey($courseId)->update([
                        'is_featured' => true,
                        'sort_order' => $index + 1,
                    ]);
                }
            }
        });

        return $this->index();
    }
}
