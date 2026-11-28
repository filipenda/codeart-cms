<?php

namespace Codeart\Core\Tables;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * An icon button at the end of a row: a link (GET) or, for POST/PUT/PATCH/DELETE, a form with the confirm dialog.
 * It is hidden when the user lacks `permission`; the route it points to must authorize the request again.
 */
final class RowAction
{
    public ?Closure $visible = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        public readonly Closure $url,
        public readonly ?string $permission = null,
        public readonly string $method = 'GET',
        public readonly ?string $confirm = null,
    ) {}

    /** @param Closure(Model): string $url */
    public static function edit(Closure $url, ?string $permission = null): self
    {
        return new self('edit', __('codeart::table.edit'), 'pencil', $url, $permission);
    }

    /** @param Closure(Model): string $url */
    public static function duplicate(Closure $url, ?string $permission = null): self
    {
        return new self('duplicate', __('codeart::table.duplicate'), 'copy', $url, $permission, 'POST', __('codeart::table.duplicate_confirm'));
    }

    /** @param Closure(Model): string $url */
    public static function delete(Closure $url, ?string $permission = null): self
    {
        return new self('delete', __('codeart::table.delete'), 'trash-2', $url, $permission, 'DELETE', __('codeart::table.delete_confirm'));
    }

    /** @param Closure(Model): string $url */
    public static function make(string $key, string $label, string $icon, Closure $url, ?string $permission = null, string $method = 'GET', ?string $confirm = null): self
    {
        return new self($key, $label, $icon, $url, $permission, $method, $confirm);
    }

    /** Hide it for some rows: `fn (Model $row): bool`. */
    public function visibleWhen(Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /** Destructive actions (delete, force-delete, revoke) get the danger variant: icon color only, never filled at rest. */
    public function danger(): bool
    {
        return in_array($this->key, ['delete', 'force-delete', 'revoke'], true);
    }

    public function isGet(): bool
    {
        return strtoupper($this->method) === 'GET';
    }

    public function allowedFor(?Authenticatable $user): bool
    {
        return $this->permission === null || ($user !== null && Gate::forUser($user)->allows($this->permission));
    }

    public function shownFor(?Authenticatable $user, Model $row): bool
    {
        return $this->allowedFor($user) && ($this->visible === null || (bool) ($this->visible)($row));
    }
}
