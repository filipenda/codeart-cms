<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\InvalidInvitation;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserInvitation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __construct(private readonly CreateAdminUser $create, private readonly ActivityLog $log) {}

    /**
     * Single use: the row is locked while it is checked and consumed.
     *
     * @throws InvalidInvitation
     */
    public function __invoke(string $token, string $password, ?string $locale = null): Model
    {
        $user = DB::transaction(function () use ($token, $password, $locale): Model {
            $invitation = UserInvitation::query()->where('token_hash', UserInvitation::hashToken($token))->lockForUpdate()->first();
            if ($invitation === null || ! $invitation->isUsable() || Admin::findByEmail($invitation->email) !== null) {
                throw new InvalidInvitation;
            }

            // The inviter may have lost rank since: the invitation only holds if they could still send it.
            $inviter = $invitation->invited_by === null ? null : Admin::userModel()::query()->find($invitation->invited_by);
            $inviter = $inviter instanceof Authenticatable ? $inviter : null;
            if ($invitation->invited_by !== null && $inviter === null) {
                throw new InvalidInvitation;
            }
            foreach ($invitation->roles as $role) {
                if (! RoleRank::canAssign($inviter, $role)) {
                    throw new InvalidInvitation;
                }
            }

            $user = ($this->create)($invitation->name, $invitation->email, $password, $invitation->roles, $inviter, [
                'email_verified_at' => now(),
                'invited_at' => $invitation->created_at,
                'locale' => $locale,
            ]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        $this->log->record('invitation.accepted', 'Invitation accepted', $user, $user, [], 'users');

        return $user;
    }
}
