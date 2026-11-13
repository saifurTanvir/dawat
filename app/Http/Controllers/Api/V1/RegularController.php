<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regular\SaveRegularRequest;
use App\Http\Resources\RegularResource;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPause;
use App\Models\Order;
use App\Services\Meta\MetaEvents;
use App\Services\Orders\Billing;
use App\Services\Orders\BookingPlanner;
use App\Support\Geo\GeoPoint;
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

        return RegularResource::make($customer->load(self::WITH));
    }

    /** GET /api/v1/regular/bills — F39: what is owed and the bills. */
    public function bills(Request $request, Billing $billing): JsonResponse
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        $unbilled = $billing->unbilled($customer);

        return response()->json(['data' => [
            'billing' => $customer->billing,
            'billing_label' => Customer::BILLING_LABELS[$customer->billing] ?? '',
            'unbilled_paisa' => $billing->unbilledTotal($customer),
            'unbilled_boxes' => $unbilled->count(),
            'bills' => $customer->bills()->where('status', '!=', 'void')->limit(12)->get()->map(fn (Bill $b) => [
                'number' => $b->number,
                'period_from' => $b->period_from->toDateString(),
                'period_to' => $b->period_to->toDateString(),
                'total_paisa' => $b->total_paisa,
                'paid_paisa' => $b->paid_paisa,
                'due_paisa' => $b->duePaisa(),
                'status' => $b->status,
                'status_label' => $b->statusLabel(),
                'url' => $b->publicUrl(),
            ]),
        ]]);
    }

    private const WITH = ['user', 'area', 'pairing.kitchen', 'pairing.shahidar', 'todayOrders', 'upcomingPauses'];

    /** POST /api/v1/regular/orders/{order}/skip — "আজ না" before pickup. */
    public function skip(Request $request, Order $order, BookingPlanner $planner): RegularResource
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($order->customer_id === $customer->id, 404);

        $planner->cancel($order, 'customer', 'গ্রাহক: আজ না');

        return RegularResource::make($customer->fresh(self::WITH))->additional(['message' => 'আজকের খাবার বাদ দেওয়া হয়েছে।']);
    }

    /** POST /api/v1/regular/orders/{order}/accept — "নেব" at today's higher price (F30). */
    public function accept(Request $request, Order $order, BookingPlanner $planner): RegularResource
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($order->customer_id === $customer->id, 404);

        $planner->accept($order);

        return RegularResource::make($customer->fresh(self::WITH))->additional(['message' => 'বুক হয়েছে। ডেলিভারি কোড SMS-এ আসছে।']);
    }

    /** POST /api/v1/regular/pauses {starts_on, ends_on} — days off. */
    public function pause(Request $request, BookingPlanner $planner): RegularResource
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], [
            'starts_on.required' => 'কবে থেকে, তারিখ দিন।',
            'ends_on.required' => 'কবে পর্যন্ত, তারিখ দিন।',
            'ends_on.after_or_equal' => 'শেষের তারিখ শুরুর পরে হতে হবে।',
        ]);

        $planner->pause($customer, $data['starts_on'], $data['ends_on'], $request->user()->id);

        return RegularResource::make($customer->fresh(self::WITH))->additional(['message' => 'ওই দিনগুলোতে খাবার আসবে না।']);
    }

    /** DELETE /api/v1/regular/pauses/{pause} */
    public function unpause(Request $request, CustomerPause $pause, BookingPlanner $planner): RegularResource
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($pause->customer_id === $customer->id, 404);
        $pause->delete();
        $planner->bookLate($customer);

        return RegularResource::make($customer->fresh(self::WITH))->additional(['message' => 'বিরতি তুলে নেওয়া হয়েছে।']);
    }

    /** PUT /api/v1/regular — ask, ask again after stopping, or change the booking. */
    /** F55: the home pin is optional and only ever replaced, never sent back. */
    private static function setPin(Customer $customer, array $data): void
    {
        if (isset($data['lat'], $data['lng'])) {
            $customer->location = GeoPoint::make($data['lat'], $data['lng']);
        }
    }

    public function save(SaveRegularRequest $request, BookingPlanner $planner, MetaEvents $meta): RegularResource
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
                'max_price_paisa' => isset($data['max_price_taka']) ? (int) $data['max_price_taka'] * 100 : null,
                'weekdays' => array_map('intval', array_values($data['weekdays'])),
            ];

            if (! $customer) {
                $customer = new Customer([...$fields, 'user_id' => $user->id, 'source' => 'app']);
                self::setPin($customer, $data);
                $customer->save();

                return $customer;
            }
            self::setPin($customer, $data);
            if ($customer->status === CustomerStatus::Stopped) {
                $fields['status'] = CustomerStatus::Waiting;
            }
            $customer->fill($fields)->save();

            return $customer;
        });

        // F45: a meal added for today gets a box from a post already up.
        $planner->bookLate($customer);

        return RegularResource::make($customer->fresh(self::WITH))
            ->additional([
                'message' => $customer->wasRecentlyCreated || $customer->status === CustomerStatus::Waiting
                    ? 'দাওয়াত টিম আপনার এলাকার একজন গৃহিণী আর শাহিদার ঠিক করে ফোন দেবে।'
                    : 'বুকিং বদলানো হয়েছে।',
                // F54: a new regular customer is what the customer ads are paid for.
                'meta_event' => $customer->wasRecentlyCreated
                    ? $meta->record('Subscribe', 'regular-'.$customer->id, $request, $user, custom: ['content_name' => 'regular'])
                    : null,
            ]);
    }
}
