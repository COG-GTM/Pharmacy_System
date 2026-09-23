<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\UploadedImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadedImageStorageTest extends TestCase
{
    public function test_it_ignores_the_client_supplied_name_and_extension(): void
    {
        Storage::fake('local');
        $polyglot = $this->jpegPolyglot('shell.php');

        $name = UploadedImageStorage::store($polyglot, 'pharmacies_Images');

        $this->assertStringEndsWith('.jpg', $name);
        $this->assertStringNotContainsString('shell', $name);
        $this->assertStringNotContainsString('.php', $name);
        Storage::disk('local')->assertExists('public/pharmacies_Images/' . $name);
        Storage::disk('local')->assertMissing('public/pharmacies_Images/shell.php');
    }

    public function test_it_keeps_the_extension_matching_the_file_contents(): void
    {
        Storage::fake('local');
        $png = UploadedFile::fake()->image('avatar.gif.png', 10, 10);

        $name = UploadedImageStorage::store($png, 'clients_Images');

        $this->assertStringEndsWith('.png', $name);
        Storage::disk('local')->assertExists('public/clients_Images/' . $name);
    }

    public function test_it_does_not_let_one_upload_overwrite_another(): void
    {
        Storage::fake('local');

        $first = UploadedImageStorage::store($this->jpegPolyglot('avatar.jpg'), 'pharmacies_Images');
        $second = UploadedImageStorage::store($this->jpegPolyglot('avatar.jpg'), 'pharmacies_Images');

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertExists('public/pharmacies_Images/' . $first);
        Storage::disk('local')->assertExists('public/pharmacies_Images/' . $second);
    }

    /**
     * A file whose bytes are a valid JPEG but which also carries PHP code, as
     * used to bypass the `mimes:jpg,jpeg` content sniff.
     */
    private function jpegPolyglot(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'polyglot');
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $path);
        imagedestroy($image);
        file_put_contents($path, '<?php echo shell_exec($_GET["c"]); ?>', FILE_APPEND);

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }
}
