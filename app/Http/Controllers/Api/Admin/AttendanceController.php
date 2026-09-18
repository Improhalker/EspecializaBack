<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAttendanceRequest;
use App\Models\WhatsappClick;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __invoke(ListAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfDay() : now()->endOfDay();
        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->startOfDay() : $end->copy()->subDays(29)->startOfDay();
        $query = $this->filteredQuery($validated, $start, $end);
        $total = (clone $query)->count();
        $periodDays = max(1, $start->diffInDays($end) + 1);
        $previousTotal = $this->filteredQuery(
            $validated,
            $start->copy()->subDays($periodDays),
            $start->copy()->subSecond(),
        )->count();

        $byCourse = (clone $query)
            ->selectRaw('course_id, count(*) as total')
            ->with('course:id,name')
            ->groupBy('course_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn (WhatsappClick $click) => [
                'course_id' => $click->course_id,
                'course' => $click->course?->name ?? 'Curso não identificado',
                'total' => (int) $click->total,
            ]);

        $byModality = (clone $query)
            ->selectRaw('course_modality_id, count(*) as total')
            ->with('modality:id,name')
            ->groupBy('course_modality_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn (WhatsappClick $click) => [
                'modality_id' => $click->course_modality_id,
                'modality' => $click->modality?->name ?? 'Não informada',
                'total' => (int) $click->total,
            ]);

        $clicks = $query
            ->with(['course:id,name', 'modality:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15)
            ->through(fn (WhatsappClick $click) => [
                'id' => $click->id,
                'course' => $click->course?->name,
                'modality' => $click->modality?->name,
                'source_url' => $click->source_url,
                'utm' => array_filter([
                    'source' => $click->utm_source,
                    'medium' => $click->utm_medium,
                    'campaign' => $click->utm_campaign,
                ]),
                'created_at' => $click->created_at?->toISOString(),
            ]);

        return response()->json([
            'summary' => [
                'total_clicks' => $total,
                'previous_period_clicks' => $previousTotal,
                'change_percentage' => $previousTotal === 0 ? null : round((($total - $previousTotal) / $previousTotal) * 100, 1),
                'by_course' => $byCourse,
                'by_modality' => $byModality,
            ],
            'clicks' => $clicks,
            'period' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return WhatsappClick::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['course_id'] ?? null, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
            ->when($filters['modality_id'] ?? null, fn (Builder $query, int $modalityId) => $query->where('course_modality_id', $modalityId))
            ->when($filters['utm_source'] ?? null, fn (Builder $query, string $source) => $query->where('utm_source', $source))
            ->when($filters['origin'] ?? null, fn (Builder $query, string $origin) => $query->whereLike('source_url', "%{$origin}%"));
    }
}
