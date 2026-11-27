<?php

namespace Codeart\Core\Tables;

/** What a bulk action did: rows done, failed and skipped, or that it was queued instead (counts unknown until it runs). */
final class BulkResult
{
    public function __construct(
        public int $done = 0,
        public int $failed = 0,
        public int $skipped = 0,
        public readonly bool $queued = false,
        public readonly int $matched = 0,
    ) {}

    public static function queued(int $matched): self
    {
        return new self(queued: true, matched: $matched);
    }

    public function message(): string
    {
        return $this->queued
            ? __('codeart::table.bulk_queued', ['count' => number_format($this->matched, 0, ',', '.')])
            : __('codeart::table.bulk_done', ['done' => $this->done, 'failed' => $this->failed, 'skipped' => $this->skipped]);
    }

    /** success when nothing failed, warning when some did, danger when everything did. */
    public function tone(): string
    {
        return match (true) {
            $this->queued => 'info',
            $this->failed === 0 => 'success',
            $this->done === 0 && $this->skipped === 0 => 'danger',
            default => 'warning',
        };
    }
}
