<?php

namespace Codeart\Core\Media;

use RuntimeException;

/** A file the library refuses. The message is already translated and safe to show; `$reason` is the machine key. */
final class MediaRejected extends RuntimeException
{
    /** @param array<string, scalar> $params */
    public function __construct(public readonly string $reason, array $params = [])
    {
        parent::__construct((string) __('codeart::media.reject.'.$reason, $params));
    }
}
