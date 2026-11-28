<?php

namespace Codeart\Core\Console;

use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaRegenerateCommand extends Command
{
    protected $signature = 'media:regenerate {--id=* : Only these media ids} {--missing : Only images without sizes}';

    protected $description = 'Regenerate the pre-generated sizes (thumb, medium, large, WebP) of the images in the media library';

    public function handle(MediaVariants $variants): int
    {
        $query = Media::query()->where('mime', 'like', 'image/%');
        if ($this->option('id') !== []) {
            $query->whereIn('id', array_map('intval', (array) $this->option('id')));
        }
        if ($this->option('missing')) {
            $query->whereNull('variants');
        }

        $done = 0;
        $failed = 0;
        $query->chunkById(100, function ($chunk) use ($variants, &$done, &$failed): void {
            foreach ($chunk as $media) {
                if (! Storage::disk($media->disk)->exists($media->path)) {
                    $this->components->warn("#{$media->id} {$media->original_name}: the original file is missing, skipped.");
                    $failed++;

                    continue;
                }
                $variants->generate($media);
                $done++;
            }
        });

        $this->components->info("Regenerated {$done} image(s)".($failed > 0 ? ", {$failed} skipped." : '.'));

        return self::SUCCESS;
    }
}
