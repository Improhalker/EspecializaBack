<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SiteAnalyticsReport
{
    public function build(array $filters): array
    {
        $timezone = config('analytics.timezone', 'America/Sao_Paulo');
        $end = Carbon::parse($filters['end_date'] ?? now($timezone)->toDateString(), $timezone)->endOfDay();
        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date'], $timezone)->startOfDay() : $end->copy()->subDays(29)->startOfDay();
        $days = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;
        $query = $this->visits($filters, $start, $end);
        $previous = $this->visits($filters, $start->copy()->subDays($days), $start->copy()->subMicrosecond());
        $currentStats = $this->summary($query);
        $previousStats = $this->summary($previous);
        $interactions = $this->interactions($query);
        $currentStats['interactions'] = (clone $interactions)->count();
        $currentStats['whatsapp_clicks'] = (clone $interactions)->where('i.kind', 'whatsapp')->count();
        $contactSessions = (clone $interactions)->where('i.kind', 'whatsapp')->distinct()->count('v.session_hash');
        $currentStats['contact_rate'] = $currentStats['sessions'] ? round($contactSessions / $currentStats['sessions'] * 100, 1) : 0;
        $currentStats['views_change'] = $previousStats['views'] ? round(($currentStats['views'] - $previousStats['views']) / $previousStats['views'] * 100, 1) : null;

        $pages = (clone $query)->select('path')
            ->selectRaw('count(*) as views, count(distinct session_hash) as sessions, avg(active_ms) / 1000.0 as active_seconds, avg(scroll_depth) as scroll_depth')
            ->groupBy('path')->orderByDesc('views')->orderBy('path')->limit(15)->get();
        $buttons = (clone $interactions)->select('i.kind', 'i.label', 'v.path')->selectRaw('count(*) as total')
            ->groupBy('i.kind', 'i.label', 'v.path')->orderByDesc('total')->orderBy('i.label')->limit(15)->get();
        $sources = (clone $query)->selectRaw("coalesce(utm_source, referrer_host, 'Direto') as label, count(*) as total")
            ->groupByRaw("coalesce(utm_source, referrer_host, 'Direto')")->orderByDesc('total')->limit(10)->get();
        $campaigns = (clone $query)->whereNotNull('utm_campaign')->selectRaw('utm_campaign as label, count(*) as total')
            ->groupBy('utm_campaign')->orderByDesc('total')->limit(10)->get();
        $devices = (clone $query)->selectRaw('device as label, count(*) as total')->groupBy('device')->orderByDesc('total')->get();
        $depth = (clone $query)->selectRaw('sum(case when scroll_depth >= 25 then 1 else 0 end) as d25, sum(case when scroll_depth >= 50 then 1 else 0 end) as d50, sum(case when scroll_depth >= 75 then 1 else 0 end) as d75, sum(case when scroll_depth >= 90 then 1 else 0 end) as d90, sum(case when scroll_depth >= 100 then 1 else 0 end) as d100')->first();
        $performance = (clone $query)->selectRaw('avg(lcp_ms) as lcp_ms, count(lcp_ms) as lcp_samples, avg(inp_ms) as inp_ms, count(inp_ms) as inp_samples, avg(cls) as cls, count(cls) as cls_samples')->first();
        $scroll = array_map(fn (int $level): array => [
            'depth' => $level, 'total' => (int) ($depth->{'d'.$level} ?? 0),
            'percentage' => $currentStats['views'] ? round(($depth->{'d'.$level} ?? 0) / $currentStats['views'] * 100, 1) : 0,
        ], [25, 50, 75, 90, 100]);

        return [
            'period' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'timezone' => $timezone],
            'summary' => $currentStats, 'previous' => $previousStats,
            'timeline' => $this->timeline($query, $start, $days, $timezone),
            'pages' => $pages, 'buttons' => $buttons, 'sources' => $sources, 'campaigns' => $campaigns,
            'devices' => $devices, 'scroll' => $scroll, 'performance' => $performance,
            'meta' => [
                'enabled' => config('analytics.enabled'), 'excluded_ips' => config('analytics.excluded_ips'),
                'first_recorded_at' => DB::table('analytics_page_views')->min('started_at'),
                'paths' => DB::table('analytics_page_views')->where('started_at', '>=', now()->subDays(90))->select('path')->distinct()->orderBy('path')->limit(100)->pluck('path'),
            ],
        ];
    }

    private function visits(array $filters, Carbon $start, Carbon $end): Builder
    {
        return DB::table('analytics_page_views')
            ->whereBetween('started_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->when($filters['path'] ?? null, fn (Builder $query, string $path) => $query->where('path', $path))
            ->when($filters['device'] ?? null, fn (Builder $query, string $device) => $query->where('device', $device))
            ->when($filters['utm_source'] ?? null, fn (Builder $query, string $source) => $query->where('utm_source', $source));
    }

    private function interactions(Builder $visits): Builder
    {
        return DB::table('analytics_interactions as i')->joinSub($visits, 'v', 'v.id', '=', 'i.page_view_id');
    }

    private function summary(Builder $query): array
    {
        $stats = (clone $query)->selectRaw('count(*) as views, count(distinct session_hash) as sessions, avg(active_ms) / 1000.0 as active_seconds, avg(scroll_depth) as scroll_depth, sum(case when scroll_depth >= 75 then 1 else 0 end) as deep_views')->first();

        return [
            'views' => (int) $stats->views, 'sessions' => (int) $stats->sessions,
            'active_seconds' => round((float) ($stats->active_seconds ?? 0), 1),
            'scroll_depth' => round((float) ($stats->scroll_depth ?? 0), 1),
            'deep_read_rate' => $stats->views ? round(($stats->deep_views ?? 0) / $stats->views * 100, 1) : 0,
        ];
    }

    private function timeline(Builder $query, Carbon $start, int $days, string $timezone): array
    {
        $expression = DB::getDriverName() === 'pgsql' ? 'date(started_at AT TIME ZONE ?)' : 'date(started_at, ?)';
        $bindings = DB::getDriverName() === 'pgsql' ? [$timezone] : [$start->format('P')];
        $rows = (clone $query)->selectRaw($expression.' as day, count(*) as views, count(distinct session_hash) as sessions', $bindings)
            ->groupByRaw('1')->orderBy('day')->get()->keyBy('day');
        $series = [];
        for ($index = 0; $index < $days; $index++) {
            $date = $start->copy()->addDays($index)->toDateString();
            $series[] = ['date' => $date, 'views' => (int) ($rows[$date]->views ?? 0), 'sessions' => (int) ($rows[$date]->sessions ?? 0)];
        }

        return $series;
    }
}
