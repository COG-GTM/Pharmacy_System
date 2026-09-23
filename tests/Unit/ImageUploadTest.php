<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    public function test_it_stores_a_jpeg_php_polyglot_under_a_random_jpg_name()
    {
        Storage::fake('local');

        $upload = $this->uploadedFile('shell.php', $this->jpegBytes() . '<?php system($_GET["c"]); ?>');

        $name = ImageUpload::store($upload, 'public/pharmacies_Images');

        $this->assertStringEndsWith('.jpg', $name);
        $this->assertNotSame('shell.php', $name);
        Storage::disk('local')->assertExists('public/pharmacies_Images/' . $name);
        Storage::disk('local')->assertMissing('public/pharmacies_Images/shell.php');
    }

    public function test_it_rejects_a_file_that_is_not_an_allowed_image()
    {
        Storage::fake('local');

        $upload = $this->uploadedFile('shell.php', '<?php system($_GET["c"]); ?>');

        $this->expectException(ValidationException::class);

        try {
            ImageUpload::store($upload, 'public/pharmacies_Images');
        } finally {
            $this->assertEmpty(Storage::disk('local')->allFiles('public/pharmacies_Images'));
        }
    }

    public function test_it_fails_when_the_file_cannot_be_written()
    {
        Storage::fake('local');

        $upload = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$this->temporaryFile($this->jpegBytes()), 'avatar.jpg', 'image/jpeg', null, true])
            ->onlyMethods(['getMimeType', 'storeAs'])
            ->getMock();
        $upload->method('getMimeType')->willReturn('image/jpeg');
        $upload->method('storeAs')->willReturn(false);

        $this->expectException(RuntimeException::class);

        ImageUpload::store($upload, 'public/pharmacies_Images');
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        return new UploadedFile($this->temporaryFile($contents), $name, 'image/jpeg', null, true);
    }

    private function temporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return $path;
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
