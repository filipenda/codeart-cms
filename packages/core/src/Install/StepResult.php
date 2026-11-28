<?php

namespace Codeart\Core\Install;

/** Outcome of one run step. `message` is a key of install.run.* texts (plain language); `warning` = done, but something optional failed. */
final readonly class StepResult
{
    public function __construct(
        public string $step,
        public bool $ok,
        public ?string $message = null,
        public bool $warning = false,
        public ?string $detail = null,
    ) {}
}
