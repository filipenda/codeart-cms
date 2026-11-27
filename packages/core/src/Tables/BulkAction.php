<?php

namespace Codeart\Core\Tables;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * An action applied to the selected rows (the ones ticked on the page, or every row matching the filter).
 * `handle` runs once per row inside a chunk: return true (done), false (failed) or null (skipped); an exception
 * counts as failed. A `destructive` action needs a confirmation token (see `TableController`). The permission is checked on the server by `BulkActionRunner`, not only in the UI.
 */
final class BulkAction
{
    /** @param Closure(Model): (bool|null) $handle */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Closure $handle,
        public readonly ?string $permission = null,
        public readonly ?string $confirm = null,
        public readonly bool $queueable = true,
        public readonly bool $destructive = false,
    ) {}

    /** @param Closure(Model): (bool|null) $handle */
    public static function make(string $key, string $label, Closure $handle, ?string $permission = null, ?string $confirm = null, bool $queueable = true, bool $destructive = false): self
    {
        return new self($key, $label, $handle, $permission, $confirm, $queueable, $destructive);
    }

    /**
     * A destructive action (delete, anonymize ...) never runs without a server-issued confirmation token (TB11): the first
     * request only renders the confirmation step. `$confirm` is the text shown there.
     *
     * @param  Closure(Model): (bool|null)  $handle
     */
    public static function destructive(string $key, string $label, Closure $handle, ?string $permission = null, ?string $confirm = null, bool $queueable = true): self
    {
        return new self($key, $label, $handle, $permission, $confirm, $queueable, true);
    }

    public function allowedFor(?Authenticatable $user): bool
    {
        return $this->permission === null || ($user !== null && Gate::forUser($user)->allows($this->permission));
    }
}
