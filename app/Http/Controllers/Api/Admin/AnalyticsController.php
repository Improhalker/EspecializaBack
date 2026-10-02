<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAnalyticsRequest;
use App\Services\SiteAnalyticsReport;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __invoke(ListAnalyticsRequest $request, SiteAnalyticsReport $report): JsonResponse
    {
        return response()->json($report->build($request->validated()))->header('Cache-Control', 'private, no-store');
    }
}
