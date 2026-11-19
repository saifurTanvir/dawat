<?php

namespace App\Services\Regular;

use App\Enums\OrderStatus;
use App\Jobs\SendOfficeDropCode;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Order;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\BoxLabels;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Services\Push\Push;
use App\Support\ContactHours;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F68: an office's boxes for a meal travel as one drop. The office's contact
 * person gets one code when the first box is booked; the shahidar picks up a
 * cook's boxes for the office in one go (one bag sticker), and hands the
 * whole drop over once — with that code, by the contact scanning the bag, or
 * a plain tap an admin checks later.
 */
class OfficeDrops
{
    public function __construct(private readonly Delivery $delivery) {}

    /** Called when a regular box is made: puts an office employee's box in the office's drop. */
    public function attach(Order $order): void
    {
        $company = $order->customer?->company;
        if (! $company || ! $company->is_active) {
            return;
        }

        $drop = OfficeDrop::firstOrCreate([
            'company_id' => $company->id,
            'service_date' => $order->service_date->toDateString(),
            'meal_slot' => $order->meal_slot->value,
        ]);
        $order->forceFill(['office_drop_id' => $drop->id])->save();

        if (! $drop->code_hash && $order->status === OrderStatus::Booked) {
            $code = (string) random_int(1000, 9999);
            $drop->forceFill(['code_hash' => Hash::make($code)])->save();
            SendOfficeDropCode::dispatch($drop->id, $code)->afterCommit();
        }
    }

    /**
     * Picks up all of one cook's booked boxes for the drop; the bag's sticker
     * (if scanned) is tied to the drop.
     */
    public function pickup(OfficeDrop $drop, Kitchen $kitchen, User $shahidar, ?string $stickerInput): int
    {
        return DB::transaction(function () use ($drop, $kitchen, $shahidar, $stickerInput) {
            $orders = $drop->orders()->where('kitchen_id', $kitchen->id)->where('shahidar_id', $shahidar->id)
                ->where('status', OrderStatus::Booked)->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'এই গৃহিণীর কাছে তোলার মতো বক্স নেই।']);
            }

            if (filled($stickerInput)) {
                $code = Sticker::normalize($stickerInput);
                $sticker = $code ? Sticker::where('code', $code)->lockForUpdate()->first() : null;
                if (! $sticker) {
                    throw ValidationException::withMessages(['sticker' => 'এই স্টিকার চেনা যাচ্ছে না। আবার স্ক্যান করুন বা নম্বরটা লিখুন।']);
                }
                if ($sticker->order_id || ($sticker->office_drop_id && $sticker->office_drop_id !== $drop->id)) {
                    throw ValidationException::withMessages(['sticker' => 'এই স্টিকার অন্য বক্স বা ব্যাগে লাগানো হয়েছে। নতুন একটা নিন।']);
                }
                $sticker->forceFill(['office_drop_id' => $drop->id, 'bound_at' => now(), 'bound_by' => $shahidar->id])->save();
            }

            foreach ($orders as $order) {
                $this->delivery->pickup($order, $shahidar, null);
            }

            return $orders->count();
        });
    }

    /**
     * Hands the drop's picked-up boxes to the office at once.
     *
     * @param  string  $method  office_code | office_scan | office_tap
     */
    public function deliver(OfficeDrop $drop, User $by, ?string $code, bool $cashReceived = false, bool $byContact = false): int
    {
        return DB::transaction(function () use ($drop, $by, $code, $cashReceived, $byContact) {
            $drop = OfficeDrop::whereKey($drop->id)->lockForUpdate()->firstOrFail();
            if ($byContact) {
                $method = 'office_scan';
            } elseif (filled($code)) {
                if (! $drop->code_hash || ! Hash::check(trim($code), $drop->code_hash)) {
                    throw ValidationException::withMessages(['code' => 'কোড মেলেনি। অফিসের দায়িত্বপ্রাপ্ত লোকের SMS-এর ৪ অঙ্কের কোডটা আবার দেখুন।']);
                }
                $method = 'office_code';
            } else {
                $method = 'office_tap';
            }

            $orders = $drop->orders()->where('status', OrderStatus::PickedUp)->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'এই অফিসের কোনো বক্স এখন পথে নেই। আগে পিকআপ করুন।']);
            }
            $company = $drop->company;
            foreach ($orders as $order) {
                $order = $this->delivery->deliverInDrop($order, $by, $method);
                // F79: where in the office their box is.
                Push::toUser($order->recipient(), 'খাবার অফিসে পৌঁছেছে',
                    ($order->drop_row ? 'আপনার বক্স: '.BoxLabels::spot($order).' · ' : '').'বক্স '.$order->code().' · '.$company?->name.($company?->drop_note ? ' ('.$company->drop_note.')' : ''),
                    '/regular', 'spot-'.$order->id);
                if ($cashReceived && ! $byContact && ! $order->customer?->paysByBill()) {
                    app(Settlements::class)->markPaid($order, $by);
                }
            }

            if (! $drop->orders()->whereIn('status', [OrderStatus::Booked, OrderStatus::PickedUp, OrderStatus::Asking])->exists()) {
                $drop->forceFill(['delivered_at' => now(), 'confirm_method' => $method, 'confirmed_by' => $by->id])->save();
            }

            return $orders->count();
        });
    }

    /** Today's drops with boxes for this shahidar, for their list. */
    public function forShahidar(User $shahidar): Collection
    {
        return OfficeDrop::query()
            ->whereDate('service_date', BookingPlanner::today())
            ->whereHas('orders', fn ($q) => $q->where('shahidar_id', $shahidar->id))
            ->with(['company.contact', 'company.area', 'stickers', 'orders' => fn ($q) => $q->where('shahidar_id', $shahidar->id)->with(['customer.user', 'kitchen.user', 'kitchen.area'])->orderBy('seq')])
            ->get()
            ->map(fn (OfficeDrop $drop) => self::row($drop));
    }

    public static function row(OfficeDrop $drop): array
    {
        $company = $drop->company;
        $orders = $drop->orders->whereNotIn('status', [OrderStatus::Cancelled]);
        $cash = $orders->filter(fn (Order $o) => ! $o->customer?->paysByBill() && ! $o->paid_at && $o->status !== OrderStatus::Failed)
            ->sum(fn (Order $o) => $o->totalPaisa());

        return [
            'id' => $drop->id,
            'company' => $company->name,
            'address' => $company->address,
            'area' => $company->area?->name,
            'note' => $company->drop_note,
            'meal_slot_label' => $drop->meal_slot->label(),
            'contact' => $company->contact ? [
                'name' => $company->contact->name,
                'phone' => $company->contact->phone,
                'card' => ContactHours::card($company->contact, [$drop->meal_slot], 'আসসালামু আলাইকুম, দাওয়াত থেকে বলছি। অফিসের '.$drop->meal_slot->label().'র খাবার নিয়ে।'),
            ] : null,
            'boxes' => $orders->count(),
            'plates' => $orders->sum('plates'),
            'booked' => $orders->where('status', OrderStatus::Booked)->count(),
            'picked' => $orders->where('status', OrderStatus::PickedUp)->count(),
            'delivered' => $orders->where('status', OrderStatus::Delivered)->count(),
            'done' => $drop->delivered_at !== null,
            'method_label' => OfficeDrop::METHODS[$drop->confirm_method] ?? null,
            'cash_paisa' => $cash,
            'bags' => $drop->stickers->map(fn (Sticker $s) => $s->displayCode())->values(),
            'kitchens' => $orders->groupBy('kitchen_id')->map(fn (Collection $boxes) => [
                'id' => $boxes->first()->kitchen_id,
                'name' => $boxes->first()->kitchen->display_name,
                'phone' => $boxes->first()->kitchen->user->phone,
                'address' => $boxes->first()->kitchen->pickup_address ?? $boxes->first()->kitchen->area?->name,
                'booked' => $boxes->where('status', OrderStatus::Booked)->count(),
                'boxes' => $boxes->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code(),
                    'name' => $o->customer?->user?->name ?: 'কর্মী',
                    'spot' => BoxLabels::spot($o),
                    'plates' => $o->plates,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                ])->values(),
            ])->values(),
        ];
    }
}
