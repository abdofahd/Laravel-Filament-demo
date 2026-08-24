<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A titled bundle of uploaded files.
 *
 * Files are stored by spatie/laravel-medialibrary on the "public" disk, so
 * they are reachable over HTTP through the public/storage symlink.
 */
class Attachment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
    ];

    public function registerMediaCollections(): void
    {
        // No conversions are registered on purpose: image conversions need an
        // image driver (GD/Imagick) and, by default, a queue worker. This
        // deployment has neither, and a queued conversion would never run.
        $this->addMediaCollection('files')
            ->useDisk(config('media-library.disk_name'));
    }

    /**
     * Total size of every file attached to this record, in bytes.
     */
    public function totalSize(): int
    {
        return (int) $this->getMedia('files')->sum('size');
    }

    /**
     * Human readable size for a single media item.
     */
    public static function formatSize(Media|int $size): string
    {
        $bytes = $size instanceof Media ? (int) $size->size : $size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
