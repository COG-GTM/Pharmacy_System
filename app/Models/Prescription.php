<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Prescription extends Model
{
    use HasFactory;

    public const DISK = 'prescriptions';
    public const LEGACY_DISK = 'public';
    public const LEGACY_DIRECTORY = 'images/prescriptions';

    protected $fillable = [
        'image',
        'order_id'
    ];

    protected $hidden = [
        'image'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public static function storeFor(Order $order, UploadedFile $file): self
    {
        $name = $file->hashName();
        $file->storeAs('', $name, ['disk' => self::DISK]);

        return self::create([
            'order_id' => $order->id,
            'image' => $name,
        ]);
    }

    public function fileName(): string
    {
        return basename((string) $this->image);
    }

    public function deleteFile(): void
    {
        Storage::disk(self::DISK)->delete($this->fileName());
        Storage::disk(self::LEGACY_DISK)->delete(self::LEGACY_DIRECTORY . '/' . $this->fileName());
    }

    public function fileResponse(): StreamedResponse
    {
        $name = $this->fileName();
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if (Storage::disk(self::DISK)->exists($name)) {
            return Storage::disk(self::DISK)->response($name, $name, $headers);
        }

        $legacyPath = self::LEGACY_DIRECTORY . '/' . $name;
        if (Storage::disk(self::LEGACY_DISK)->exists($legacyPath)) {
            return Storage::disk(self::LEGACY_DISK)->response($legacyPath, $name, $headers);
        }

        abort(404);
    }
}
