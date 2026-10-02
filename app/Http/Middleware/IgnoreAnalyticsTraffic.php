<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IgnoreAnalyticsTraffic
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = preg_replace('/^::ffff:/', '', $request->ip() ?? '');
        $bot = preg_match('/bot|crawler|spider|headless|lighthouse|pagespeed|monitor|uptimerobot/i', $request->userAgent() ?? '');
        $origins = config('analytics.allowed_origins', []);
        if (app()->environment('local')) {
            $origins = array_merge($origins, ['http://localhost:5173', 'http://127.0.0.1:5173']);
        }
        if ($request->header('Origin') && ! in_array(rtrim($request->header('Origin'), '/'), $origins, true)) {
            return response()->noContent();
        }
        if (! config('analytics.enabled') || in_array($ip, config('analytics.excluded_ips', []), true) || $bot || $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') {
            return response()->noContent();
        }

        return $next($request);
    }
}
