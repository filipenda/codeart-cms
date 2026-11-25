<?php

namespace Codeart\Core\Settings;

use InvalidArgumentException;

/** Never carries the offending value: it may be a secret. */
class InvalidSettingValue extends InvalidArgumentException
{
    public function __construct(public readonly string $key, string $reason)
    {
        parent::__construct("Invalid value for setting [{$key}]: {$reason}");
    }
}
