<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Sticker;
use App\Models\User;
use App\Support\DhakaTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * F79: before leaving home the shahidar taps "স্টিকার প্রিন্ট": every box of
 * theirs today that has no sticker yet gets one, printed with the customer's
 * name, phone and address (two to a 75 × 100 mm label). An office's boxes
 * also get a spot — row and column — where the box will sit at the office.
 */
class BoxLabels
{
    /** Boxes in a row at the office drop point. */
    public const COLUMNS = 5;

    /** @return array{batch: string, count: int}|null null when every box already has a sticker */
    public function print(User $shahidar): ?array
    {
        return DB::transaction(function () use ($shahidar) {
            $orders = Order::query()
                ->where('shahidar_id', $shahidar->id)
                ->whereDate('service_date', BookingPlanner::today())
                ->whereIn('status', [OrderStatus::Booked, OrderStatus::PickedUp])
                ->doesntHave('sticker')
                ->with(['customer.user', 'buyer'])
                ->lockForUpdate()
                ->get();
            if ($orders->isEmpty()) {
                return null;
            }

            $this->placeOfficeBoxes($orders);

            $batch = 'p'.now(DhakaTime::TZ)->format('ymd-Hi').'-'.$shahidar->id;
            foreach ($orders->sortBy(fn (Order $o) => [$o->office_drop_id ? 0 : 1, $o->office_drop_id, $o->drop_row, $o->drop_col, $o->seq]) as $order) {
                Sticker::create([
                    'code' => Sticker::newCode(),
                    'batch' => $batch,
                    'order_id' => $order->id,
                    'printed_at' => now(),
                    'printed_by' => $shahidar->id,
                ]);
            }

            return ['batch' => $batch, 'count' => $orders->count()];
        });
    }

    /** Next free spots in each office drop, by name, row by row. */
    private function placeOfficeBoxes(Collection $orders): void
    {
        foreach ($orders->whereNotNull('office_drop_id')->groupBy('office_drop_id') as $dropId => $boxes) {
            $taken = Order::where('office_drop_id', $dropId)->whereNotNull('drop_row')
                ->get(['drop_row', 'drop_col'])->map(fn ($o) => ($o->drop_row - 1) * self::COLUMNS + ($o->drop_col - 1))->all();
            $n = 0;
            foreach ($boxes->sortBy(fn (Order $o) => $o->recipient()->name ?? '') as $order) {
                if ($order->drop_row) {
                    continue;
                }
                while (in_array($n, $taken, true)) {
                    $n++;
                }
                $order->forceFill(['drop_row' => intdiv($n, self::COLUMNS) + 1, 'drop_col' => $n % self::COLUMNS + 1])->save();
                $taken[] = $n;
            }
        }
    }

    /** A private link to the printable sheet, for a couple of hours. */
    public static function url(string $batch): string
    {
        return URL::temporarySignedRoute('stickers.print', now()->addHours(3), ['batch' => $batch], absolute: false);
    }

    /** Today's printed sheets of this shahidar, newest first, to print again. */
    public function today(User $shahidar): Collection
    {
        return Sticker::query()
            ->where('printed_by', $shahidar->id)
            ->where('printed_at', '>=', DhakaTime::startOfDay())
            ->selectRaw('batch, COUNT(*) AS n, MAX(printed_at) AS at')
            ->groupBy('batch')->orderByDesc('at')->get()
            ->map(fn ($row) => [
                'batch' => $row->batch,
                'count' => (int) $row->n,
                'at' => Carbon::parse($row->at)->toIso8601String(),
                'url' => self::url($row->batch),
            ]);
    }

    /** "সারি ২ · কলাম ৩" for an office box. */
    public static function spot(Order $o): ?string
    {
        return $o->drop_row ? 'সারি '.Order::banglaDigits($o->drop_row).' · কলাম '.Order::banglaDigits($o->drop_col) : null;
    }
}
