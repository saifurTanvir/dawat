<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Support\Seo;
use Illuminate\View\View;

/** F39: a customer's bill, opened from a link (WhatsApp, notification); no login needed. */
class BillController extends Controller
{
    public function show(string $token): View
    {
        $bill = Bill::where('token', $token)->where('status', '!=', 'void')
            ->with(['customer.user', 'orders.kitchen', 'payments'])->firstOrFail();

        return view('bills.show', [
            'bill' => $bill,
            'meta' => Seo::meta('বিল '.$bill->number.' — দাওয়াত', 'দাওয়াতের বিল', null, 'noindex,nofollow') + ['canonical' => null],
        ]);
    }
}
