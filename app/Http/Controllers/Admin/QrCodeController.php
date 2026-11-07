<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Support\QrImage;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Printable QR material for admins. Routes are protected by the admin panel's auth. */
class QrCodeController extends Controller
{
    public function poster(QrCode $qr): View
    {
        return view('admin.qr-poster', [
            'qr' => $qr->load('area'),
            'svg' => QrImage::svg($qr->url()),
        ]);
    }

    public function png(QrCode $qr): Response
    {
        return response(QrImage::png($qr->url()), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="dawat-qr-'.$qr->code.'.png"',
        ]);
    }
}
