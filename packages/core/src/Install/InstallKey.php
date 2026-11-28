<?php

namespace Codeart\Core\Install;

use Illuminate\Support\Facades\RateLimiter;

/** The one-time key from the file `install.key` in the application folder; proves the person has file access to the server. */
class InstallKey
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 600;

    public function __construct(private readonly InstallPaths $paths) {}

    public function exists(): bool
    {
        return is_file($this->paths->key) && $this->expected() !== '';
    }

    /** @return bool true when the key is right; a wrong one counts as an attempt, too many attempts lock the gate for a while */
    public function attempt(string $given, string $throttleId): bool
    {
        if ($this->tooManyAttempts($throttleId)) {
            return false;
        }
        $expected = $this->expected();
        if ($expected !== '' && hash_equals($expected, trim($given))) {
            RateLimiter::clear($this->limiterKey($throttleId));

            return true;
        }
        RateLimiter::hit($this->limiterKey($throttleId), self::DECAY_SECONDS);

        return false;
    }

    public function tooManyAttempts(string $throttleId): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($throttleId), self::MAX_ATTEMPTS);
    }

    public function retryAfter(string $throttleId): int
    {
        return RateLimiter::availableIn($this->limiterKey($throttleId));
    }

    public function delete(): void
    {
        is_file($this->paths->key) && unlink($this->paths->key);
    }

    private function expected(): string
    {
        return is_file($this->paths->key) ? trim((string) file_get_contents($this->paths->key)) : '';
    }

    private function limiterKey(string $id): string
    {
        return 'codeart-install-key:'.sha1($id);
    }
}
