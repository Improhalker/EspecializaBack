<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnalyticsRequest;
use App\Models\AnalyticsPageView;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function store(StoreAnalyticsRequest $request): Response
    {
        $data = $request->validated();
        $page = $data['page'];
        $hash = hash_hmac('sha256', $data['session_id'], config('app.key'));
        $agent = $request->userAgent() ?? '';
        $device = preg_match('/ipad|tablet|android(?!.*mobile)/i', $agent) ? 'tablet' : (preg_match('/mobile|iphone|ipod/i', $agent) ? 'mobile' : 'desktop');

        DB::transaction(function () use ($page, $data, $hash, $device): void {
            DB::table('analytics_page_views')->insertOrIgnore([
                'id' => $page['id'], 'session_hash' => $hash, 'path' => $page['path'], 'device' => $device,
                'referrer_host' => $page['referrer_host'] ?? null, 'utm_source' => $page['utm_source'] ?? null,
                'utm_medium' => $page['utm_medium'] ?? null, 'utm_campaign' => $page['utm_campaign'] ?? null,
                'started_at' => now(), 'updated_at' => now(), 'active_ms' => 0, 'scroll_depth' => 0,
            ]);
            $visit = AnalyticsPageView::query()->whereKey($page['id'])->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($visit->session_hash, $hash) && $visit->path === $page['path'], 422, 'A visita não corresponde a esta sessão.');
            $metrics = ['updated_at' => now(), 'active_ms' => max($visit->active_ms, $page['active_ms']), 'scroll_depth' => max($visit->scroll_depth, $page['scroll_depth'])];
            foreach (['lcp_ms', 'inp_ms', 'cls'] as $metric) {
                if (isset($page[$metric])) {
                    $metrics[$metric] = max($visit->$metric ?? 0, $page[$metric]);
                }
            }
            $visit->update($metrics);
            $interactions = array_map(fn (array $interaction): array => [
                'id' => $interaction['id'], 'page_view_id' => $visit->id, 'kind' => $interaction['kind'],
                'label' => $interaction['label'], 'created_at' => now(),
            ], $data['interactions']);
            if ($interactions !== []) {
                DB::table('analytics_interactions')->insertOrIgnore($interactions);
            }
        });

        return response()->noContent();
    }
}
