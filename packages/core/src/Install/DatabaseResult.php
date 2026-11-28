<?php

namespace Codeart\Core\Install;

/** Outcome of "Test the connection". `error` is a key of install.db_error.* (null when ok). */
final readonly class DatabaseResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
        public int $tables = 0,
    ) {}
}
