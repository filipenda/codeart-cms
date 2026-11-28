<?php

namespace Codeart\Core\Tables;

use RuntimeException;

/** Too many rows for one request and no queue to hand them to: the user must narrow the filter. */
final class BulkLimitException extends RuntimeException
{
    public function __construct(public readonly int $matched, public readonly int $max)
    {
        parent::__construct(__('codeart::table.bulk_too_many', ['count' => number_format($matched, 0, ',', '.'), 'max' => number_format($max, 0, ',', '.')]));
    }
}
