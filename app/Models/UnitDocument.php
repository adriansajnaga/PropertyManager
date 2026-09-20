<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UnitDocument extends Model
{
    protected $fillable = [
        'unit_id',
        'uploaded_by',
        'title',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** Usunięcie wpisu ma zabierać ze sobą plik — inaczej zostają sieroty na dysku. */
    protected static function booted(): void
    {
        static::deleted(function (UnitDocument $document) {
            Storage::disk($document->disk())->delete($document->path);
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function disk(): string
    {
        return 'local';
    }

    public function sizeForHumans(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', ' ').' '.$units[$unit];
    }
}
