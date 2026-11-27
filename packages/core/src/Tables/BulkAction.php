<?php

namespace Codeart\Core\Tables;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * An action applied to the selected rows (the ones ticked on the page, or every row matching the filter).
 * `handle` runs once per row inside a chunk: return true (done), false (failed) or null (skipped); an exception
 * counts as failed. The permission is checked on the server by `BulkActionRunner`, not only in the UI.
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
    ) {}

    /** @param Closure(Model): (bool|null) $handle */
    public static function make(string $key, string $label, Closure $handle, ?string $permission = null, ?string $confirm = null, bool $queueable = true): self
    {
        return new self($key, $label, $handle, $permission, $confirm, $queueable);
    }

    public function allowedFor(?Authenticatable $user): bool
    {
        return $this->permission === null || ($user !== null && Gate::forUser($user)->allows($this->permission));
    }
}
