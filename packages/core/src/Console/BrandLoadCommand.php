<?php

namespace Codeart\Core\Console;

use Codeart\Core\Branding\BrandingImageUploader;
use Codeart\Core\Images\ImageProcessor;
use Codeart\Core\Settings\InvalidSettingValue;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Loads the brand files into the theme settings through the normal image upload service (so the same checks
 * and storage apply as for an upload in the admin). Meant for development and first installs.
 */
class BrandLoadCommand extends Command
{
    /** @var array<string, string> file name => setting key */
    private const array FILES = [
        'logo-light-bg.png' => 'theme.logo_light',
        'logo-dark-bg.png' => 'theme.logo_dark',
        'favicon.png' => 'theme.favicon',
    ];

    protected $signature = 'codeart:brand:load {path? : Folder with logo-light-bg.png, logo-dark-bg.png and favicon.png (default: docs/design/brand)}';

    protected $description = 'Load the logos and favicon from a folder into the appearance settings';

    public function handle(BrandingImageUploader $uploader, ImageProcessor $images): int
    {
        $directory = $this->directory();
        if ($directory === null) {
            $this->components->warn('Brand folder not found (give a path, or put the files in docs/design/brand).');

            return self::FAILURE;
        }

        $loaded = 0;
        foreach (self::FILES as $name => $key) {
            $file = $directory.'/'.$name;
            if (! is_file($file)) {
                $this->components->warn("{$name} not found in {$directory}, skipped.");

                continue;
            }
            try {
                // test mode: the file is already on disk, not an HTTP upload; the uploader still checks its content.
                $path = $uploader->store($key, new UploadedFile($file, $name, null, null, true));
                $before = $images->inspect((string) file_get_contents($file));
                $after = $images->inspect(Storage::disk((string) config('codeart.branding.disk', 'public'))->get($path) ?? '');
                $this->components->info("{$key} <- {$name} ({$before?->width}x{$before?->height} -> {$after?->width}x{$after?->height})");
                $loaded++;
            } catch (InvalidSettingValue $e) {
                $this->components->error("{$name}: {$e->getMessage()}");
            }
        }

        return $loaded > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function directory(): ?string
    {
        $given = $this->argument('path');
        $candidates = is_string($given) && $given !== ''
            ? [$given]
            : [base_path('docs/design/brand'), dirname(base_path(), 2).'/docs/design/brand'];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return rtrim((string) realpath($candidate), '/');
            }
        }

        return null;
    }
}
