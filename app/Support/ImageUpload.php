<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageUpload
{
    public const ALLOWED_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Store an uploaded image under a random server-side name with a safe
     * extension derived from its sniffed content type, and return that name.
     */
    public static function store(UploadedFile $file, string $directory, string $attribute = 'avatar_image'): string
    {
        $extension = self::ALLOWED_EXTENSIONS[(string) $file->getMimeType()] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                $attribute => 'An Image must be jpg, jpeg or png Only',
            ]);
        }

        $name = Str::uuid()->toString() . '.' . $extension;
        $file->storeAs($directory, $name);

        return $name;
    }
}
