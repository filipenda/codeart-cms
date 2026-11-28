<?php

namespace Codeart\Core\Install;

/** One line of the requirements list. `key` selects the plain-language texts (install.req.<key>.*). */
final readonly class Requirement
{
    /** @param array<string, scalar> $replace */
    public function __construct(
        public string $key,
        public bool $ok,
        public bool $required = true,
        public array $replace = [],
    ) {}
}
