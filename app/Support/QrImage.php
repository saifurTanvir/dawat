<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Renders a URL as a QR image for printing. Medium error correction survives a crease or smudge. */
final class QrImage
{
    public static function svg(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'connectPaths' => true,
        ]);

        return (new QRCode($options))->render($url);
    }

    public static function png(string $url, int $scale = 20): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'addQuietzone' => true,
            'imageTransparent' => false,
        ]);

        return (new QRCode($options))->render($url);
    }
}
