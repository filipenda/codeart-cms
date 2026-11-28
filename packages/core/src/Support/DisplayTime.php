<?php

namespace Codeart\Core\Support;

use Carbon\CarbonImmutable;
use Codeart\Core\Settings\Settings;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Lang;
use Throwable;

/**
 * Display time zone of the admin. The database keeps UTC; only what a person SEES is converted: to the signed-in
 * user's zone (`users.timezone`), else the site zone (setting `site.timezone`, default Europe/Belgrade). One instance per
 * request (singleton); `admin.access` (EnsureAdminAccess) sets the user's zone. Mails and CSV exports do not use it.
 */
class DisplayTime
{
    public const string STORAGE = 'UTC';

    private ?string $zone = null;

    private ?string $site = null;

    public function __construct(private readonly Settings $settings) {}

    public static function current(): self
    {
        return app(self::class);
    }

    /** The site zone; an invalid stored value falls back to Europe/Belgrade. */
    public function siteZone(): string
    {
        if ($this->site === null) {
            $zone = $this->settings->get('site.timezone');
            $this->site = is_string($zone) && self::isValid($zone) ? $zone : 'Europe/Belgrade'; // read once per request: tables call this per cell
        }

        return $this->site;
    }

    /** Use this zone for the rest of the request (null or an invalid identifier = the site zone). */
    public function use(?string $zone): void
    {
        $this->site = null;
        $this->zone = $zone !== null && self::isValid($zone) ? $zone : null;
    }

    public function zone(): string
    {
        return $this->zone ?? $this->siteZone();
    }

    public static function isValid(string $zone): bool
    {
        return in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    /** A stored value (UTC; string, DateTime or Carbon) in the display zone, or null for an empty/unreadable value. */
    public function convert(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : (is_numeric($value) ? CarbonImmutable::createFromTimestampUTC((int) $value) : CarbonImmutable::parse((string) $value, self::STORAGE));

            return $date->setTimezone($this->zone());
        } catch (Throwable) {
            return null;
        }
    }

    public function format(mixed $value, string $format = 'd.m.Y. H:i', string $empty = '—'): string
    {
        return $this->convert($value)?->translatedFormat($format) ?? $empty;
    }

    public function relative(mixed $value, string $empty = '—'): string
    {
        return $this->convert($value)?->diffForHumans() ?? $empty;
    }

    /** "+01:00" style offset of a zone right now (or at `$at`). */
    public static function offset(string $zone, ?DateTimeInterface $at = null): string
    {
        $seconds = (new DateTimeZone($zone))->getOffset($at ?? new \DateTimeImmutable('now', new DateTimeZone('UTC')));
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    public static function label(string $zone, ?DateTimeInterface $at = null): string
    {
        $city = str_replace('_', ' ', str_contains($zone, '/') ? substr($zone, (int) strrpos($zone, '/') + 1) : $zone);
        $key = 'codeart::timezones.'.strtolower(str_replace([' ', '/'], '_', $city));
        $name = Lang::has($key) ? (string) __($key) : $city;

        return '(UTC'.self::offset($zone, $at).') '.$name;
    }

    /**
     * Zones grouped by region for a select: [region => [identifier => "(UTC+01:00) Beograd"]], sorted by offset then name.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(?DateTimeInterface $at = null): array
    {
        $at ??= new \DateTimeImmutable('now', new DateTimeZone('UTC'));
        $groups = [];
        foreach (DateTimeZone::listIdentifiers() as $id) {
            $region = str_contains($id, '/') ? explode('/', $id, 2)[0] : $id;
            $groups[$region][$id] = [(new DateTimeZone($id))->getOffset($at), self::label($id, $at)];
        }
        foreach ($groups as $region => $zones) {
            uasort($zones, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $groups[$region] = array_map(fn (array $z): string => $z[1], $zones);
        }
        ksort($groups);

        return $groups;
    }
}
