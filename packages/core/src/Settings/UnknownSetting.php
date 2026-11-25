<?php

namespace Codeart\Core\Settings;

use InvalidArgumentException;

class UnknownSetting extends InvalidArgumentException
{
    public function __construct(string $key)
    {
        parent::__construct("Unknown setting [{$key}].");
    }
}
