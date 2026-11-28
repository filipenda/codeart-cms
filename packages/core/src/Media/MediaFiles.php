<?php

namespace Codeart\Core\Media;

use Codeart\Core\Images\ImageProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates an upload by its CONTENT and writes it to the library disk. Raw layer (no permission, no database row):
 * `StoreMedia` / `ReplaceMedia` build on it.
 */
final class MediaFiles
{
    public function __construct(
        private readonly MediaTypes $types,
        private readonly UploadLimit $limit,
        private readonly ImageProcessor $images,
    ) {}

    public function disk(): string
    {
        return (string) config('codeart.media.disk', 'public');
    }

    /**
     * Everything that must be true before a byte is stored.
     *
     * @return array{bytes: string, ext: string, mime: string, width: int|null, height: int|null, hash: string, name: string}
     */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getRealPath() === false) {
            throw new MediaRejected('upload_failed', ['limit' => UploadLimit::human($this->limit->effectiveBytes())]);
        }
        $size = (int) $file->getSize();
        if ($size === 0) {
            throw new MediaRejected('empty');
        }
        $max = $this->limit->effectiveBytes();
        if ($max > 0 && $size > $max) {
            throw new MediaRejected('too_large', ['limit' => UploadLimit::human($max)]);
        }

        $name = $this->cleanName($file->getClientOriginalName());
        if (MediaTypes::hasDangerousName($name)) {
            throw new MediaRejected('dangerous_name');
        }

        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $detected = $this->types->detect($bytes, $name);
        if ($detected === null) {
            throw new MediaRejected('type_not_allowed');
        }

        $width = $height = null;
        if (str_starts_with($detected['mime'], 'image/')) {
            $info = $this->images->probe($bytes);
            if ($info === null || $info->mime !== $detected['mime']) {
                throw new MediaRejected('corrupt_image');
            }
            $maxPixels = (int) config('codeart.media.max_pixels', 50_000_000);
            if ($info->width * $info->height > $maxPixels) {
                throw new MediaRejected('too_many_pixels', ['pixels' => (int) round($maxPixels / 1_000_000)]);
            }
            $width = $info->width;
            $height = $info->height;
        }

        return ['bytes' => $bytes, 'ext' => $detected['ext'], 'mime' => $detected['mime'], 'width' => $width, 'height' => $height, 'hash' => hash('sha256', $bytes), 'name' => $name];
    }

    /**
     * Strips image metadata (EXIF with GPS) and writes the file under uploads/YYYY/MM with a random suffix.
     *
     * @param  array{bytes: string, ext: string, mime: string, width: int|null, height: int|null, hash: string, name: string}  $checked
     * @return array{path: string, bytes: string, size: int, width: int|null, height: int|null}
     */
    public function write(array $checked): array
    {
        $bytes = $checked['bytes'];
        $width = $checked['width'];
        $height = $checked['height'];
        if ($width !== null) {
            try {
                $bytes = $this->images->stripMetadata($bytes);
                $info = $this->images->probe($bytes);
                if ($info !== null) {
                    [$width, $height] = [$info->width, $info->height]; // camera rotation may have swapped them
                }
            } catch (\Throwable $e) {
                throw new MediaRejected('corrupt_image');
            }
        }

        $base = Str::slug(pathinfo($checked['name'], PATHINFO_FILENAME));
        $base = $base === '' ? 'file' : Str::limit($base, 60, '');
        $directory = 'uploads/'.date('Y').'/'.date('m');
        $path = $directory.'/'.rtrim($base, '-').'-'.Str::lower(Str::random(8)).'.'.$checked['ext'];

        $disk = Storage::disk($this->disk());
        $this->guardUploadsDirectory($disk);
        if (! $disk->put($path, $bytes)) {
            throw new MediaRejected('store_failed');
        }

        return ['path' => $path, 'bytes' => $bytes, 'size' => strlen($bytes), 'width' => $width, 'height' => $height];
    }

    /**
     * Defence in depth for Apache hosts: nothing under uploads/ may run as a script, even if a bad file ever got in.
     * (Nginx and other servers: see docs/media.md; the library itself never stores a script type.)
     */
    private function guardUploadsDirectory(Filesystem $disk): void
    {
        if (! $disk->exists('uploads/.htaccess')) {
            $disk->put('uploads/.htaccess', "# Written by CodeArt: files in this folder are data, never code.\n"
                ."<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
                ."<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
                ."<FilesMatch \"(?i)\\.(php\\d?|phtml|phar|pht|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n"
                ."Options -ExecCGI -Indexes\n"
                ."RemoveHandler .php .phtml .phar\n"
                ."<IfModule mod_headers.c>\n  Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n");
        }
    }

    /** Only the file name, no path parts, no control characters, at most 200 characters. */
    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return mb_substr(trim($name) !== '' ? trim($name) : 'file', 0, 200);
    }
}
