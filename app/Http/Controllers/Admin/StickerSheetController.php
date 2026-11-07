<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sticker;
use App\Support\QrImage;
use Illuminate\View\View;

/** F27: an A4 sheet of blank box stickers, 4 × 8 per page. */
class StickerSheetController extends Controller
{
    public function __invoke(string $batch): View
    {
        $stickers = Sticker::where('batch', $batch)->orderBy('id')->get();
        abort_if($stickers->isEmpty(), 404);

        return view('admin.sticker-sheet', [
            'batch' => $batch,
            'stickers' => $stickers->map(fn (Sticker $s) => ['code' => $s->displayCode(), 'svg' => QrImage::svg($s->url())]),
        ]);
    }
}
