<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KitchenStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OwnKitchenResource;
use App\Models\Kitchen;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    /** GET /api/v1/kitchen — the signed-in cook's own kitchen. */
    public function show(Request $request): OwnKitchenResource|JsonResponse
    {
        $kitchen = $request->user()->kitchen?->load('area');

        if (! $kitchen) {
            return response()->json(['message' => 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।'], 404);
        }

        return OwnKitchenResource::make($kitchen);
    }

    /**
     * POST /api/v1/kitchen — "I want to cook for Dawat".
     * Creates a pending kitchen; an admin must approve it before any post goes live (F3).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->kitchen) {
            return response()->json(['message' => 'আপনার রান্নাঘর আগেই নিবন্ধিত আছে।'], 409);
        }

        $data = $request->validate(
            ['display_name' => ['required', 'string', 'min:2', 'max:60']],
            [
                'display_name.required' => 'রান্নাঘরের একটা নাম দিন, যেমন "রহিমা খালার রান্নাঘর"।',
                'display_name.min' => 'নাম কমপক্ষে ২ অক্ষরের হতে হবে।',
                'display_name.max' => 'নাম ৬০ অক্ষরের মধ্যে রাখুন।',
            ],
        );

        $kitchen = DB::transaction(function () use ($user, $data) {
            $kitchen = Kitchen::create([
                'user_id' => $user->id,
                'display_name' => $data['display_name'],
                'status' => KitchenStatus::Pending,
            ]);
            $user->assignRole(Roles::COOK);
            $user->setRelation('kitchen', $kitchen);

            return $kitchen;
        });

        return OwnKitchenResource::make($kitchen->load('area'))
            ->additional(['message' => 'নিবন্ধন হয়েছে। দাওয়াত টিম যাচাই করে অনুমোদন দিলে পোস্ট করতে পারবেন।'])
            ->response()
            ->setStatusCode(201);
    }
}
