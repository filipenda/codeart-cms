<?php

namespace Codeart\Core\Users;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\RowAction;
use Codeart\Core\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/** Open invitations (not yet accepted): resend (new link) and revoke. The token is never shown, stored or logged. */
final class InvitationsTable implements TableDefinition
{
    public const string KEY = 'user-invitations';

    public function table(): DataTable
    {
        $roleLabels = fn (string $locale): array => collect(Role::query()->where('guard_name', Admin::guardName())->pluck('name'))
            ->mapWithKeys(fn ($n): array => [(string) $n => (string) __('codeart::users.roles.'.$n, [], $locale)])->all();
        $states = ['valid' => [__('codeart::users.invitation.valid'), 'success'], 'expired' => [__('codeart::users.invitation.expired'), 'warning']];

        return DataTable::make(self::KEY, UserInvitation::query()->whereNull('accepted_at'))
            ->columns(
                Column::avatar('name', __('codeart::users.col.user'), "CONCAT(user_invitations.name, ' ', user_invitations.email)")
                    ->using(fn (Model $i): array => ['name' => (string) $i->getAttribute('name'), 'subtitle' => (string) $i->getAttribute('email')]),
                Column::text('roles', __('codeart::users.col.role'), "JSON_UNQUOTE(JSON_EXTRACT(user_invitations.roles, '\$[0]'))")
                    ->using(fn (Model $i): string => implode(', ', array_map(fn (string $r): string => (string) __('codeart::users.roles.'.$r), (array) $i->getAttribute('roles'))))
                    ->searchTranslated(fn (string $l): array => $roleLabels($l)),
                Column::status('state', __('codeart::users.col.status'), $states, "CASE WHEN user_invitations.expires_at < NOW() THEN 'expired' ELSE 'valid' END")
                    ->using(fn (Model $i): string => $i->getAttribute('expires_at')->isPast() ? 'expired' : 'valid')
                    ->searchTranslated(fn (string $l): array => ['valid' => (string) __('codeart::users.invitation.valid', [], $l), 'expired' => (string) __('codeart::users.invitation.expired', [], $l)]),
                Column::date('expires_at', __('codeart::users.invitation.expires')),
                Column::date('created_at', __('codeart::users.col.created')),
            )
            ->defaultSort('created_at', 'desc')
            ->permission('users.create')
            ->rowActions(
                RowAction::make('resend', __('codeart::users.invitation.resend'), 'mail', fn (Model $i): string => route('admin.users.invitations.resend', $i->getKey()), 'users.create', 'POST', __('codeart::users.invitation.resend_confirm'))
                    ->visibleWhen(fn (Model $i): bool => $this->mayHandOut($i)),
                RowAction::make('revoke', __('codeart::users.invitation.revoke'), 'x', fn (Model $i): string => route('admin.users.invitations.revoke', $i->getKey()), 'users.create', 'DELETE', __('codeart::users.invitation.revoke_confirm'))
                    ->visibleWhen(fn (Model $i): bool => $this->mayHandOut($i)),
            );
    }

    private function mayHandOut(Model $invitation): bool
    {
        foreach ((array) $invitation->getAttribute('roles') as $role) {
            if (! RoleRank::canAssign(Admin::currentUser(), (string) $role)) {
                return false;
            }
        }

        return true;
    }
}
