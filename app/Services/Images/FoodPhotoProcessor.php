<?php

namespace App\Services\Images;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a cook's upload into a safe, small WebP.
 *
 * Re-encoding from pixels drops ALL metadata, including the EXIF GPS position
 * many phones write into photos taken at home. Never store the original file.
 */
class FoodPhotoProcessor
{
    public const MAX_EDGE = 1600;

    public const MIN_EDGE = 300;

    /** Refuse to decode anything bigger than this (decompression-bomb guard). */
    public const MAX_SOURCE_PIXELS = 40_000_000;

    public const QUALITY = 80;

    /**
     * @return array{path: string, width: int, height: int}
     *
     * @throws UnreadablePhotoException
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $bytes = file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new UnreadablePhotoException('ছবিটা খোলা যাচ্ছে না। JPG, PNG বা WebP ছবি দিন।');
        }
        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw new UnreadablePhotoException('ছবিটা অনেক বড়। ফোনের সাধারণ ক্যামেরায় তোলা ছবি দিন।');
        }
        if (min($info[0], $info[1]) < self::MIN_EDGE) {
            throw new UnreadablePhotoException('ছবিটা খুব ছোট, ঝাপসা দেখাবে। আরেকটু কাছ থেকে তুলুন।');
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new UnreadablePhotoException('ছবিটা খোলা যাচ্ছে না। আবার তুলে দেখুন।');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $bytes);
        }

        $image = $this->fitWithin($image, self::MAX_EDGE);

        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $encoded = ob_get_clean();
        imagedestroy($image);

        if ($encoded === false || $encoded === '') {
            throw new RuntimeException('Could not encode WebP.');
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk(config('filesystems.media_disk'))->put($path, $encoded, 'public');

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    /** Phones store photos sideways and record the rotation in EXIF; bake it into the pixels. */
    private function applyExifOrientation(GdImage $image, string $bytes): GdImage
    {
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            // Mirrored variants are rare (front cameras); flip then rotate.
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $rotated = match ($orientation) {
                4 => imagerotate($image, 180, 0),
                5 => imagerotate($image, 90, 0),
                7 => imagerotate($image, -90, 0),
                default => $image,
            };
        }

        return $rotated ?: $image;
    }

    private function fitWithin(GdImage $image, int $maxEdge): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxEdge / max($w, $h));

        if ($scale >= 1) {
            return $image;
        }

        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($image);

        return $resized;
    }
}
