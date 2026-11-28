<?php

namespace Codeart\Core\Confirmation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * The server-side proof that a destructive action was confirmed in a previous step (docs/DECISIONS.md TB11).
 *
 * A token is issued by a confirmation step (a page without JS, a JSON call for the dialog with JS) and is bound to the
 * user, a scope (`bulk:<table>:<action>`, `action:<METHOD>:<path>`) and the exact payload (the ticked ids or the filter).
 * It is encrypted + authenticated (APP_KEY), expires after TTL seconds and works once (the nonce is remembered in the cache
 * until the token would have expired anyway).
 */
final class ConfirmationTokens
{
    public const int TTL = 600;

    /** @param array<string, mixed> $payload */
    public function issue(?Authenticatable $user, string $scope, array $payload = []): string
    {
        return Crypt::encryptString((string) json_encode([
            'u' => $this->userId($user),
            'h' => $this->digest($scope, $payload),
            'e' => now()->getTimestamp() + self::TTL,
            'n' => bin2hex(random_bytes(16)),
        ]));
    }

    /**
     * True once for a valid token of this user, scope and payload; false for anything else (missing, expired, tampered, reused).
     *
     * @param  array<string, mixed>  $payload
     */
    public function consume(?string $token, ?Authenticatable $user, string $scope, array $payload = []): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        if (! is_array($data) || ! isset($data['u'], $data['h'], $data['e'], $data['n'])
            || $data['u'] !== $this->userId($user)
            || ! hash_equals((string) $data['h'], $this->digest($scope, $payload))
            || (int) $data['e'] < now()->getTimestamp()) {
            return false;
        }

        // Atomic "first use wins".
        return Cache::add('codeart:confirmation:'.$data['n'], 1, self::TTL + 60);
    }

    /** Signature of a confirm-component target, so the confirmation page cannot be abused to post anywhere. */
    public function sign(string ...$parts): string
    {
        return hash_hmac('sha256', implode("\n", $parts), (string) config('app.key'));
    }

    public function validSignature(string $signature, string ...$parts): bool
    {
        return hash_equals($this->sign(...$parts), $signature);
    }

    private function userId(?Authenticatable $user): string
    {
        return $user === null ? '' : (string) $user->getAuthIdentifier();
    }

    /** @param array<string, mixed> $payload */
    private function digest(string $scope, array $payload): string
    {
        $this->sortKeys($payload);

        return hash('sha256', $scope."\n".json_encode($payload));
    }

    /** @param array<array-key, mixed> $value */
    private function sortKeys(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortKeys($item);
            }
        }
        unset($item);
        array_is_list($value) || ksort($value);
    }
}
