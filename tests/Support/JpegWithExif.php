<?php

namespace Tests\Support;

/** Builds JPEGs with hand-made EXIF (orientation + a GPS block) for tests. */
final class JpegWithExif
{
    public static function make(int $width, int $height, int $orientation = 1, bool $withGps = true): string
    {
        $img = imagecreatetruecolor($width, $height);
        // Left half red, right half blue, so rotation is visible in pixels.
        imagefilledrectangle($img, 0, 0, intdiv($width, 2), $height, imagecolorallocate($img, 220, 30, 30));
        imagefilledrectangle($img, intdiv($width, 2) + 1, 0, $width, $height, imagecolorallocate($img, 30, 30, 220));
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = ob_get_clean();

        // TIFF, little endian. IFD0 at offset 8.
        $entries = [pack('vvVv2', 0x0112, 3, 1, $orientation, 0)];
        $ifd0Count = $withGps ? 2 : 1;
        $gpsOffset = 8 + 2 + 12 * $ifd0Count + 4;
        if ($withGps) {
            $entries[] = pack('vvVV', 0x8825, 4, 1, $gpsOffset);
        }
        $tiff = 'II'.pack('vV', 42, 8).pack('v', $ifd0Count).implode('', $entries).pack('V', 0);
        if ($withGps) {
            // GPSLatitudeRef = "N" — enough to prove the GPS block is gone afterwards.
            $tiff .= pack('v', 1).pack('vvV', 0x0001, 2, 2).'N'."\0\0\0".pack('V', 0);
        }
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        // Insert right after SOI (FFD8).
        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
