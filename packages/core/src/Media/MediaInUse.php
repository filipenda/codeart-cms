<?php

namespace Codeart\Core\Media;

use RuntimeException;

/** Deleting a file that content still uses: carries the list of places. */
final class MediaInUse extends RuntimeException
{
    /** @param list<array{type: string, id: int, field: string, label: string, url: string|null}> $places */
    public function __construct(public readonly Media $media, public readonly array $places)
    {
        parent::__construct((string) __('codeart::media.in_use', ['name' => $media->original_name, 'count' => count($places)]));
    }
}
