<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class UploadedImageStorage
{
    /**
     * Extensions that may be written to the publicly served disk, keyed by the
     * extension guessed from the file contents.
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'webp' => 'webp',
    ];

    private const FALLBACK_EXTENSION = 'jpg';

    /**
     * Store an uploaded image under a random server generated name and return
     * that name. The client supplied filename and extension are never used.
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $name = Str::uuid()->toString() . '.' . self::safeExtension($file);
        $file->storeAs($disk . '/' . trim($directory, '/'), $name);

        return $name;
    }

    /**
     * Resolve a safe extension from the file contents, ignoring the client
     * supplied one.
     */
    public static function safeExtension(UploadedFile $file): string
    {
        $guessed = strtolower((string) $file->guessExtension());

        return self::ALLOWED_EXTENSIONS[$guessed] ?? self::FALLBACK_EXTENSION;
    }
}
