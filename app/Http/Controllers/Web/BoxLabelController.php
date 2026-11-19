<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sticker;
use App\Support\PhoneNumber;
use App\Support\QrImage;
use Illuminate\View\View;

/**
 * F79: the printable sheet of named box stickers — 75 × 100 mm thermal
 * labels, two stickers each. Opened from a signed link in the shahidar's
 * app, so nobody else can see the names and phones on it.
 */
class BoxLabelController extends Controller
{
    public function __invoke(string $batch): View
    {
        $stickers = Sticker::where('batch', $batch)->whereNotNull('printed_at')
            ->with(['order.customer.user', 'order.customer.area', 'order.customer.company', 'order.buyer', 'order.kitchen'])
            ->orderBy('id')->get()->filter(fn (Sticker $s) => $s->order);
        abort_if($stickers->isEmpty(), 404);

        $labels = $stickers->map(function (Sticker $s) {
            $o = $s->order;
            $user = $o->recipient();
            $address = $o->recipientAddress();
            if (! $o->isOneOff() && $o->customer?->area) {
                $address = trim($address.', '.$o->customer->area->name, ', ');
            }

            return [
                'code' => $s->displayCode(),
                'qr' => QrImage::svg($s->url()),
                'call' => $user->phone ? QrImage::svg('tel:+'.PhoneNumber::international($user->phone)) : null,
                'name' => $user->name ?: 'গ্রাহক',
                'phone' => $user->phone,
                'address' => $address,
                'box' => $o->code(),
                'meal' => $o->meal_slot->label(),
                'kitchen' => $o->kitchen?->display_name,
                'spot' => $o->drop_row ? ['row' => Order::banglaDigits($o->drop_row), 'col' => Order::banglaDigits($o->drop_col)] : null,
                'company' => $o->customer?->company?->name,
            ];
        })->values();

        return view('print.box-labels', ['batch' => $batch, 'labels' => $labels, 'pages' => $labels->chunk(2)]);
    }
}
