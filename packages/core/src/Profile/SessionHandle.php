<?php

namespace Codeart\Core\Profile;

/**
 * The opaque handle the session list puts in the HTML instead of the real session id: an HMAC of the id and the owner with
 * the app key. It cannot be reversed or forged; to use it the server walks the signed-in user's own rows and compares.
 */
final class SessionHandle
{
    public static function for(string $sessionId, int|string $userId): string
    {
        return hash_hmac('sha256', 'session|'.$userId.'|'.$sessionId, (string) config('app.key'));
    }

    public static function matches(string $handle, string $sessionId, int|string $userId): bool
    {
        return hash_equals(self::for($sessionId, $userId), $handle);
    }
}
