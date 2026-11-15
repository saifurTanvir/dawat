<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sticker;
use App\Support\QrImage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F27: blank box stickers to print. F65: by default for the 75 × 100 mm
 * thermal label printer, 4 stickers (2 × 2) on each label; ?paper=a4 gives
 * the old A4 sheet, 4 × 8 per page.
 */
class StickerSheetController extends Controller
{
    public const PAPERS = ['thermal' => 'থার্মাল লেবেল ৭৫×১০০ মিমি (প্রতি লেবেলে ৪টা)', 'a4' => 'A4 কাগজ (প্রতি পাতায় ৩২টা)'];

    public const PER_PAGE = ['thermal' => 4, 'a4' => 32];

    public function __invoke(Request $request, string $batch): View
    {
        $stickers = Sticker::where('batch', $batch)->orderBy('id')->get();
        abort_if($stickers->isEmpty(), 404);
        $paper = array_key_exists($request->query('paper'), self::PAPERS) ? $request->query('paper') : 'thermal';

        $rows = $stickers->map(fn (Sticker $s) => ['code' => $s->displayCode(), 'svg' => QrImage::svg($s->url())]);

        return view($paper === 'a4' ? 'admin.sticker-sheet' : 'admin.sticker-thermal', [
            'batch' => $batch,
            'stickers' => $rows,
            'pages' => $rows->chunk(self::PER_PAGE[$paper]),
        ]);
    }
}
