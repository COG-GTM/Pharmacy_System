<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class PrescriptionStagingTest extends TestCase
{
    public function test_a_rejected_upload_leaves_no_staged_prescription_files()
    {
        Storage::fake('local');

        $uploads = [
            $this->uploadedFile('first.jpg', $this->jpegBytes()),
            $this->uploadedFile('shell.php', '<?php system($_GET["c"]); ?>'),
        ];

        $stage = new ReflectionMethod(OrderController::class, 'storePrescriptions');
        $stage->setAccessible(true);

        $this->expectException(ValidationException::class);

        try {
            $stage->invoke(new OrderController(), $uploads);
        } finally {
            $this->assertEmpty(Storage::disk('local')->allFiles('public/images/prescriptions'));
        }
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
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
