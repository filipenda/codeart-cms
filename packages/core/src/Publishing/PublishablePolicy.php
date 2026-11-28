<?php

namespace Codeart\Core\Publishing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Abilities map 1:1 to the module permissions; `publish` is its own permission, separate from `update`. */
abstract class PublishablePolicy
{
    /** Permission prefix, the module name (`pages`). */
    abstract protected function prefix(): string;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function publish(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'publish');
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'delete');
    }

    private function allows(Authenticatable $user, string $action): bool
    {
        return method_exists($user, 'can') && $user->can($this->prefix().'.'.$action);
    }
}
