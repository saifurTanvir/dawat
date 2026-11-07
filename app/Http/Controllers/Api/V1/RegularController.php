<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regular\SaveRegularRequest;
use App\Http\Resources\RegularResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** F25: a buyer asks for regular home food, and later sees who feeds and delivers. */
class RegularController extends Controller
{
    /** GET /api/v1/regular */
    public function show(Request $request): JsonResponse|RegularResource
    {
        $customer = Customer::where('user_id', $request->user()->id)->first();
        if (! $customer) {
            return response()->json(['data' => null]);
        }

        return RegularResource::make($customer->load(['user', 'area', 'pairing.kitchen', 'pairing.shahidar']));
    }

    /** PUT /api/v1/regular — ask, ask again after stopping, or change the booking. */
    public function save(SaveRegularRequest $request): RegularResource
    {
        $data = $request->validated();
        $user = $request->user();
        $customer = Customer::where('user_id', $user->id)->first();

        if ($customer?->pairing && (int) $data['area_id'] !== $customer->area_id) {
            throw ValidationException::withMessages(['area_id' => 'এলাকা বদলাতে আপনার শাহিদার বা দাওয়াত টিমকে বলুন।']);
        }

        $customer = DB::transaction(function () use ($user, $customer, $data) {
            $user->forceFill(['name' => trim($data['name'])])->save();
            $fields = [
                'area_id' => $data['area_id'],
                'address' => trim($data['address']),
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                'meal_slots' => array_values($data['meal_slots']),
                'plates' => $data['plates'],
                'weekdays' => array_map('intval', array_values($data['weekdays'])),
            ];

            if (! $customer) {
                return Customer::create([...$fields, 'user_id' => $user->id, 'source' => 'app']);
            }
            if ($customer->status === CustomerStatus::Stopped) {
                $fields['status'] = CustomerStatus::Waiting;
            }
            $customer->fill($fields)->save();

            return $customer;
        });

        return RegularResource::make($customer->fresh(['user', 'area', 'pairing.kitchen', 'pairing.shahidar']))
            ->additional(['message' => $customer->wasRecentlyCreated || $customer->status === CustomerStatus::Waiting
                ? 'দাওয়াত টিম আপনার এলাকার একজন গৃহিণী আর শাহিদার ঠিক করে ফোন দেবে।'
                : 'বুকিং বদলানো হয়েছে।']);
    }
}
