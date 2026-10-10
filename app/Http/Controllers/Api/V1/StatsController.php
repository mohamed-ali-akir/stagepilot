<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApplicationStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/stats: reply rate, average reply delay, per week and per sector.
 */
class StatsController extends Controller
{
    public function __invoke(Request $request, ApplicationStats $stats): JsonResponse
    {
        return response()->json(['data' => $stats->forUser($request->user())]);
    }
}
