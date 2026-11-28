<?php

namespace Codeart\Core\Privacy;

use Codeart\Core\Settings\Settings;

/**
 * The one place that decides what happens to an IP address (setting `security.ip_storage`).
 * full = keep as is; anonymized = IPv4 last octet 0, IPv6 last 80 bits 0; off = nothing stored or shown.
 * The login rate limiter does NOT use this: it needs the raw IP and keeps it only in the cache.
 */
final class IpPrivacy
{
    public const string FULL = 'full';

    public const string ANONYMIZED = 'anonymized';

    public const string OFF = 'off';

    public function __construct(private readonly Settings $settings) {}

    public function mode(): string
    {
        $mode = $this->settings->get('security.ip_storage');

        return in_array($mode, [self::FULL, self::ANONYMIZED, self::OFF], true) ? $mode : self::FULL;
    }

    public function enabled(): bool
    {
        return $this->mode() !== self::OFF;
    }

    /** The value to write to the database (null when nothing may be stored). */
    public function store(?string $ip): ?string
    {
        return match ($this->mode()) {
            self::OFF => null,
            self::ANONYMIZED => self::anonymize($ip),
            default => $ip,
        };
    }

    /** The value to show; applies the mode to rows stored earlier under a different mode. */
    public function display(?string $stored): ?string
    {
        return $this->store($stored);
    }

    public static function anonymize(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return $ip;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return null; // not an address: never keep unknown text
        }
        if (strlen($bin) === 4) {
            return inet_ntop(substr($bin, 0, 3)."\0") ?: null;
        }

        return inet_ntop(substr($bin, 0, 6).str_repeat("\0", 10)) ?: null; // keep the first 48 bits, zero the last 80
    }
}
