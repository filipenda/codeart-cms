<?php

namespace Codeart\Core\Media\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaFiles;
use Codeart\Core\Media\MediaRejected;
use Codeart\Core\Media\MediaVariants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Replaces the file behind a record, keeping its id, texts, folder and every place it is used. The kind of file must stay the same. */
final class ReplaceMedia
{
    public function __construct(private readonly MediaFiles $files, private readonly MediaVariants $variants, private readonly ActivityLog $log) {}

    public function __invoke(Media $media, UploadedFile $file, ?Authenticatable $user = null): Media
    {
        $checked = $this->files->inspect($file);
        if (str_starts_with($checked['mime'], 'image/') !== $media->isImage()) {
            throw new MediaRejected('replace_kind');
        }

        $stored = $this->files->write($checked);
        $oldPath = $media->path;
        $this->variants->purge($media);
        $media->forceFill([
            'path' => $stored['path'],
            'original_name' => $checked['name'],
            'mime' => $checked['mime'],
            'size' => $stored['size'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'hash' => $checked['hash'],
        ])->save();
        Storage::disk($media->disk)->delete($oldPath);
        $this->variants->generate($media, $stored['bytes']);
        $this->log->record('media.replaced', 'File replaced', $media, $user instanceof Model ? $user : null, ['name' => $media->original_name], 'content');

        return $media;
    }
}
