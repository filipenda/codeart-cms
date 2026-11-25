<?php

namespace Codeart\Core\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** A user's own sessions (database session driver): list them, revoke one, revoke all but the current. */
class UserSessions
{
    /** @return list<array{id: string, ip_address: ?string, user_agent: ?string, last_active: CarbonImmutable, current: bool}> newest first */
    public function list(int|string $userId, ?string $currentId = null): array
    {
        return DB::table('sessions')->where('user_id', $userId)->orderByDesc('last_activity')->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'last_active' => CarbonImmutable::createFromTimestamp((int) $row->last_activity),
                'current' => $row->id === $currentId,
            ])->all();
    }

    /** Only ever deletes a session that belongs to this user. */
    public function revoke(int|string $userId, string $sessionId): bool
    {
        return DB::table('sessions')->where('user_id', $userId)->where('id', $sessionId)->delete() > 0;
    }

    public function revokeOthers(int|string $userId, string $currentId): int
    {
        return DB::table('sessions')->where('user_id', $userId)->where('id', '!=', $currentId)->delete();
    }

    public function revokeAll(int|string $userId): int
    {
        return DB::table('sessions')->where('user_id', $userId)->delete();
    }
}
