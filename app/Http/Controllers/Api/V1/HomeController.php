<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Home\HomeSummary;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /** GET /api/v1/home — F52: what the first screen shows a new visitor. */
    public function __invoke(HomeSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->get()]);
    }
}
