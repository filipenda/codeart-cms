<?php

namespace Codeart\Core\Media\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaInUse;
use Codeart\Core\Media\MediaUsage;
use Codeart\Core\Media\MediaUsageRow;
use Codeart\Core\Media\MediaVariants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a file: refused with {@see MediaInUse} while something uses it, unless `$force` (the user confirmed): then the
 * references are cleared. The row is soft deleted (history, activity log) and the files are removed from the disk.
 */
final class DeleteMedia
{
    public function __construct(private readonly MediaVariants $variants, private readonly ActivityLog $log) {}

    public function __invoke(Media $media, ?Authenticatable $user = null, bool $force = false): void
    {
        $places = MediaUsage::places($media);
        if ($places !== [] && ! $force) {
            throw new MediaInUse($media, $places);
        }

        $count = MediaUsage::count($media);
        MediaUsageRow::query()->where('media_id', $media->id)->delete();
        $this->variants->purge($media);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        $this->log->record('media.deleted', 'File deleted', $media, $user instanceof Model ? $user : null, ['name' => $media->original_name, 'forced' => $force && $count > 0, 'usages_cleared' => $force ? $count : 0], 'content');
    }
}
