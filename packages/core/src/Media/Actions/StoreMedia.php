<?php

namespace Codeart\Core\Media\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Media\DuplicateMedia;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaFiles;
use Codeart\Core\Media\MediaVariants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload one file into the library. `$duplicate`: `ask` throws {@see DuplicateMedia} when the same bytes exist,
 * `reuse` returns the existing record, `keep` stores another copy. Raw layer: the controller checks `media.upload`.
 */
final class StoreMedia
{
    public function __construct(private readonly MediaFiles $files, private readonly MediaVariants $variants, private readonly ActivityLog $log) {}

    public function __invoke(UploadedFile $file, ?Authenticatable $user = null, ?int $folderId = null, string $duplicate = 'ask'): Media
    {
        $checked = $this->files->inspect($file);

        if ($duplicate !== 'keep') {
            $existing = Media::query()->where('hash', $checked['hash'])->orderBy('id')->first();
            if ($existing !== null) {
                if ($duplicate === 'reuse') {
                    return $existing;
                }
                throw new DuplicateMedia($existing);
            }
        }

        $stored = $this->files->write($checked);
        try {
            $media = Media::query()->create([
                'disk' => $this->files->disk(),
                'path' => $stored['path'],
                'original_name' => $checked['name'],
                'mime' => $checked['mime'],
                'size' => $stored['size'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'hash' => $checked['hash'],
                'folder_id' => $folderId,
                'uploaded_by' => $user?->getAuthIdentifier(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk($this->files->disk())->delete($stored['path']);
            throw $e;
        }

        $this->variants->generate($media, $stored['bytes']);
        $this->log->record('media.uploaded', 'File uploaded', $media, $user instanceof Model ? $user : null, ['name' => $media->original_name, 'mime' => $media->mime, 'size' => $media->size], 'content');

        return $media;
    }
}
