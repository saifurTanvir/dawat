<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Http\Controllers\Controller;
use App\Models\BoxReview;
use App\Services\Reviews\BoxReviews;
use App\Services\Reviews\Sales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F47: "যা বিক্রি করেছি" — the cook's sales, earnings and reviews (no customer names). */
class MySalesController extends Controller
{
    /** GET /api/v1/my/sales */
    public function index(Request $request, Sales $sales): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');

        return response()->json(['data' => $sales->forKitchen($kitchen, (int) min(60, max(7, (int) $request->query('days', 14))))]);
    }

    /** POST /api/v1/my/reviews/{review}/reply */
    public function reply(Request $request, BoxReview $review, BoxReviews $reviews): JsonResponse
    {
        abort_unless($review->kitchen_id === $request->user()->kitchen?->id, 404);
        $data = $request->validate(['reply' => ['required', 'string', 'min:2', 'max:500']], ['reply.required' => 'উত্তর লিখুন।']);
        $reviews->reply($review, $request->user(), 'cook', $data['reply']);

        return response()->json(['data' => BoxReviews::row($review->fresh(['order', 'replier'])), 'message' => 'উত্তর দেওয়া হয়েছে, গ্রাহক দেখতে পাবেন।']);
    }
}
