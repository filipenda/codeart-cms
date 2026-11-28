<?php

namespace Codeart\Core\Users;

use Codeart\Core\Auth\Actions\ApproveUser;
use Codeart\Core\Auth\Actions\DeactivateUser;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Profile\Avatar;
use Codeart\Core\Tables\BulkAction;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\Filter;
use Codeart\Core\Tables\RowAction;
use Codeart\Core\Tables\TableDefinition;
use Codeart\Core\Users\Actions\DeleteUser;
use Codeart\Core\Users\Actions\UnblockUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * The users list (`users`): every column sorts, searches (translated texts included) and filters; rows the actor may not
 * manage show no actions and are skipped by bulk actions (the policy decides, on the server, per row).
 */
final class UsersTable implements TableDefinition
{
    public const string KEY = 'users';

    public function table(): DataTable
    {
        $model = Admin::userModel();
        $table = (new $model)->getTable();
        $roles = $this->roleLabels(app()->getLocale());
        $roleSql = "(SELECT MAX(r.name) FROM model_has_roles mhr INNER JOIN roles r ON r.id = mhr.role_id WHERE mhr.model_id = {$table}.id AND mhr.model_type = '".addslashes($model)."')";
        $statusLabels = fn (string $locale): array => array_map(fn (array $o): string => $o[0], UserStatus::options($locale));
        $roleLabels = fn (string $locale): array => $this->roleLabels($locale);

        return DataTable::make(self::KEY, $model::query())
            ->columns(
                Column::avatar('name', __('codeart::users.col.user'), "CONCAT({$table}.name, ' ', {$table}.email)")
                    ->using(fn (Model $u): array => ['name' => (string) $u->getAttribute('name'), 'subtitle' => (string) $u->getAttribute('email'), 'image' => Avatar::url($u)]),
                Column::badge('role', __('codeart::users.col.role'), array_map(fn (string $l): array => [$l, 'primary'], $roles), $roleSql)
                    ->using(fn (Model $u): ?string => $this->topRole($u))->searchTranslated($roleLabels),
                Column::status('status', __('codeart::users.col.status'), UserStatus::options(), UserStatus::sql($table))
                    ->using(fn (Model $u): string => UserStatus::of($u))->searchTranslated($statusLabels),
                Column::boolean('two_factor', __('codeart::users.col.two_factor'), "({$table}.two_factor_secret IS NOT NULL AND {$table}.two_factor_confirmed_at IS NOT NULL)")
                    ->using(fn (Model $u): bool => Admin::staff($u)->hasTwoFactorEnabled())->searchable(false)->exportUsing(fn (Model $u): string => Admin::staff($u)->hasTwoFactorEnabled() ? __('codeart::table.yes') : __('codeart::table.no')),
                Column::date('last_login_at', __('codeart::users.col.last_login')),
                Column::date('created_at', __('codeart::users.col.created')),
            )
            ->filters(
                Filter::select('role', __('codeart::users.col.role'), $roles, $roleSql),
                Filter::select('status', __('codeart::users.col.status'), array_map(fn (array $o): string => $o[0], array_diff_key(UserStatus::options(), ['deleted' => 1])), UserStatus::sql($table)),
                Filter::boolean('two_factor', __('codeart::users.col.two_factor')),
                Filter::select('trashed', __('codeart::users.filter.deleted'), ['only' => __('codeart::users.filter.only_deleted')])
                    ->using(fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull($q->getModel()->qualifyColumn('deleted_at'))),
            )
            ->with('roles')
            ->defaultSort('created_at', 'desc')
            ->permission('users.view')
            ->export('users')
            ->rowActions(...$this->rowActions())
            ->bulkActions(...$this->bulkActions());
    }

    /** @return list<RowAction> */
    private function rowActions(): array
    {
        $url = fn (Model $u): string => route('admin.users.edit', $u->getKey());
        $act = fn (string $name): \Closure => fn (Model $u): string => route('admin.users.'.$name, $u->getKey());

        return [
            RowAction::make('edit', __('codeart::table.edit'), 'pencil', $url, 'users.view')->visibleWhen(fn (Model $u): bool => ! Admin::staff($u)->trashed() && $this->allows('update', $u)),
            RowAction::make('approve', __('codeart::users.action.approve'), 'check', $act('approve'), 'users.approve', 'POST', __('codeart::users.confirm.approve'))
                ->visibleWhen(fn (Model $u): bool => UserStatus::of($u) === 'pending' && $this->allows('approve', $u)),
            RowAction::make('block', __('codeart::users.action.block'), 'lock', $act('block'), 'users.block', 'POST', __('codeart::users.confirm.block'))
                ->visibleWhen(fn (Model $u): bool => ! Admin::staff($u)->trashed() && (bool) $u->getAttribute('is_active') && $this->allows('block', $u)),
            RowAction::make('unblock', __('codeart::users.action.unblock'), 'rotate-ccw', $act('unblock'), 'users.block', 'POST', __('codeart::users.confirm.unblock'))
                ->visibleWhen(fn (Model $u): bool => ! Admin::staff($u)->trashed() && ! $u->getAttribute('is_active') && $u->getAttribute('approved_at') !== null && $this->allows('block', $u)),
            RowAction::make('restore', __('codeart::users.action.restore'), 'rotate-ccw', $act('restore'), 'users.delete', 'POST', __('codeart::users.confirm.restore'))
                ->visibleWhen(fn (Model $u): bool => Admin::staff($u)->trashed() && $this->allows('restore', $u)),
            RowAction::make('delete', __('codeart::users.action.delete'), 'trash-2', fn (Model $u): string => route('admin.users.destroy', $u->getKey()), 'users.delete', 'DELETE', __('codeart::users.confirm.delete'))
                ->visibleWhen(fn (Model $u): bool => ! Admin::staff($u)->trashed() && $this->allows('delete', $u)),
            RowAction::make('force-delete', __('codeart::users.action.force_delete'), 'trash-2', $act('force-destroy'), 'users.force-delete', 'DELETE', __('codeart::users.confirm.force_delete'))
                ->visibleWhen(fn (Model $u): bool => Admin::staff($u)->trashed() && $this->allows('forceDelete', $u)),
        ];
    }

    /** @return list<BulkAction> */
    private function bulkActions(): array
    {
        $each = function (string $ability, \Closure $do, ?\Closure $only = null): \Closure {
            return function (Model $u) use ($ability, $do, $only): ?bool {
                $actor = Admin::currentUser();
                if ($actor === null || ($only !== null && ! $only($u)) || ! Gate::forUser($actor)->allows($ability, $u)) {
                    return null; // the policy forbids it for this row: skipped and counted, never executed
                }
                $do($u, $actor);

                return true;
            };
        };

        return [
            BulkAction::make('approve', __('codeart::users.action.approve'), $each('approve', fn (Model $u, Authenticatable $a) => app(ApproveUser::class)($u, $a), fn (Model $u): bool => $this->pendingOnly($u)), 'users.approve', __('codeart::users.confirm.approve_bulk'), queueable: false),
            BulkAction::destructive('block', __('codeart::users.action.block'), $each('block', fn (Model $u, Authenticatable $a) => app(DeactivateUser::class)($u, $a), fn (Model $u): bool => ! Admin::staff($u)->trashed()), 'users.block', __('codeart::users.confirm.block_bulk'), queueable: false),
            BulkAction::make('unblock', __('codeart::users.action.unblock'), $each('block', fn (Model $u, Authenticatable $a) => app(UnblockUser::class)($u, $a), fn (Model $u): bool => ! Admin::staff($u)->trashed() && ! $u->getAttribute('is_active') && $u->getAttribute('approved_at') !== null), 'users.block', __('codeart::users.confirm.unblock'), queueable: false),
            BulkAction::destructive('delete', __('codeart::users.action.delete'), $each('delete', fn (Model $u, Authenticatable $a) => app(DeleteUser::class)($u, $a)), 'users.delete', __('codeart::users.confirm.delete_bulk'), queueable: false),
        ];
    }

    private function pendingOnly(Model $u): bool
    {
        return UserStatus::of($u) === 'pending';
    }

    private function allows(string $ability, Model $u): bool
    {
        $actor = Admin::currentUser();

        return $actor !== null && Gate::forUser($actor)->allows($ability, $u);
    }

    private function topRole(Model $u): ?string
    {
        $names = Admin::staff($u)->getRoleNames()->all();
        usort($names, fn (string $a, string $b): int => RoleRank::of($b) <=> RoleRank::of($a));

        return $names[0] ?? null;
    }

    /** @return array<string, string> role => label in the locale */
    private function roleLabels(string $locale): array
    {
        $out = [];
        foreach (Role::query()->where('guard_name', Admin::guardName())->orderBy('name')->pluck('name') as $name) {
            $out[(string) $name] = (string) __('codeart::users.roles.'.$name, [], $locale);
        }

        return $out;
    }
}
