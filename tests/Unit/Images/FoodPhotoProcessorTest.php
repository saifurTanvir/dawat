<?php

namespace Tests\Unit\Images;

use App\Services\Images\FoodPhotoProcessor;
use App\Services\Images\UnreadablePhotoException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JpegWithExif;
use Tests\TestCase;

class FoodPhotoProcessorTest extends TestCase
{
    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
    }

    private function upload(string $bytes, string $name = 'photo.jpg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_the_fixture_really_contains_gps_exif(): void
    {
        $bytes = JpegWithExif::make(800, 600, 6);
        $exif = exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));

        $this->assertSame(6, $exif['Orientation']);
        $this->assertSame('N', $exif['GPSLatitudeRef']);
    }

    public function test_it_strips_metadata_and_stores_webp(): void
    {
        $result = (new FoodPhotoProcessor)->store($this->upload(JpegWithExif::make(800, 600, 1)), 'posts/1');

        $stored = Storage::disk(config('filesystems.media_disk'))->get($result['path']);
        $this->assertStringStartsWith('RIFF', $stored);
        $this->assertStringContainsString('WEBP', substr($stored, 0, 16));
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('GPS', $stored);
        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertStringStartsWith('posts/1/', $result['path']);
    }

    public function test_it_applies_exif_rotation(): void
    {
        // Sensor image is landscape; orientation 6 means "display rotated 90° clockwise".
        $result = (new FoodPhotoProcessor)->store($this->upload(JpegWithExif::make(800, 600, 6)), 'posts/1');

        $this->assertSame(600, $result['width']);
        $this->assertSame(800, $result['height']);

        $img = imagecreatefromstring(Storage::disk(config('filesystems.media_disk'))->get($result['path']));
        // Red (left of sensor) ends up on top after a clockwise turn.
        $top = imagecolorsforindex($img, imagecolorat($img, 300, 50));
        $this->assertGreaterThan(150, $top['red']);
        $this->assertLessThan(100, $top['blue']);
    }

    public function test_it_shrinks_large_photos(): void
    {
        $result = (new FoodPhotoProcessor)->store($this->upload(JpegWithExif::make(4000, 3000, 1, false)), 'posts/1');

        $this->assertSame(1600, $result['width']);
        $this->assertSame(1200, $result['height']);
    }

    public function test_tiny_photos_are_refused(): void
    {
        $this->expectException(UnreadablePhotoException::class);

        (new FoodPhotoProcessor)->store($this->upload(JpegWithExif::make(200, 150)), 'posts/1');
    }

    public function test_non_images_are_refused(): void
    {
        $this->expectException(UnreadablePhotoException::class);

        (new FoodPhotoProcessor)->store($this->upload('<?php echo 1;', 'evil.jpg'), 'posts/1');
    }
}
