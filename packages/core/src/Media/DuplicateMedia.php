<?php

namespace Codeart\Core\Media;

use RuntimeException;

/** The same bytes are already in the library; the caller may reuse `$existing` or upload anyway. */
final class DuplicateMedia extends RuntimeException
{
    public function __construct(public readonly Media $existing)
    {
        parent::__construct((string) __('codeart::media.duplicate', ['name' => $existing->original_name]));
    }
}
