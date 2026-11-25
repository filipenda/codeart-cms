<?php

namespace Codeart\Core\Settings;

use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Typed settings stored in the database. Everything is read from ONE cache entry (plain file cache is
 * enough: no tags), which is forgotten on every write. When the database or cache is not ready
 * (install time) reads fall back to the definition defaults.
 *
 * This is the raw layer for CLI, seeders and internal code: no permission or lock checks
 * (see SettingsManager for those). Secrets are encrypted at rest and decrypted by get().
 */
class Settings
{
    public const string CACHE_KEY = 'codeart.settings';

    public const string NO_LOCALE = '*';

    /** @var array{values: array<string, array<string, mixed>>, locks: list<string>}|null */
    private ?array $memo = null;

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function registry(): SettingsRegistry
    {
        return $this->registry;
    }

    public function get(string $key, ?string $locale = null): mixed
    {
        $definition = $this->registry->definition($key);
        $values = $this->data()['values'][$key] ?? [];

        foreach ($this->localesToTry($definition, $locale) as $candidate) {
            if (array_key_exists($candidate, $values)) {
                return $this->decode($definition, $values[$candidate]);
            }
        }

        return $definition->default();
    }

    /** Is a value stored (not just the default)? */
    public function has(string $key, ?string $locale = null): bool
    {
        $definition = $this->registry->definition($key);

        return array_key_exists($this->storageLocale($definition, $locale), $this->data()['values'][$key] ?? []);
    }

    /**
     * Validate and store. A null value removes the stored one (the default applies again).
     *
     * @throws UnknownSetting|InvalidSettingValue
     */
    public function set(string $key, mixed $value, ?string $locale = null): void
    {
        $this->write([[$key, $value, $locale]]);
    }

    /**
     * Store several non-translatable settings in one go, so cross-field rules are checked against
     * the final state (e.g. a new default locale together with the new enabled list).
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $this->write(array_map(fn (string $key, mixed $value): array => [$key, $value, null], array_keys($values), array_values($values)));
    }

    public function forget(string $key, ?string $locale = null): void
    {
        $this->set($key, null, $locale);
    }

    /**
     * All settings of a group (default locale view), key => value. Secrets are decrypted: do not show these.
     *
     * @return array<string, mixed>
     */
    public function group(string $group, ?string $locale = null): array
    {
        $out = [];
        foreach ($this->registry->group($group) as $key => $definition) {
            $out[$key] = $this->get($key, $definition->translatable ? $locale : null);
        }

        return $out;
    }

    /** What the UI/CLI may print: secrets become a mask (or null when unset), everything else as is. */
    public function masked(string $key, ?string $locale = null): mixed
    {
        $definition = $this->registry->definition($key);
        if (! $definition->secret) {
            return $this->get($key, $locale);
        }

        return $this->get($key, $locale) === $definition->default() ? null : '********';
    }

    public function isLocked(string $key): bool
    {
        $this->registry->definition($key);

        return in_array($key, $this->data()['locks'], true);
    }

    public function lock(string $key): void
    {
        $this->registry->definition($key);
        DB::table('settings_locks')->upsert([['key' => $key, 'created_at' => now(), 'updated_at' => now()]], ['key'], ['updated_at']);
        $this->flush();
    }

    public function unlock(string $key): void
    {
        $this->registry->definition($key);
        DB::table('settings_locks')->where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        $this->memo = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // the cache store is not ready; nothing cached, nothing stale
        }
    }

    /**
     * @param  list<array{0: string, 1: mixed, 2: string|null}>  $entries  key, value, locale
     */
    private function write(array $entries): void
    {
        $pending = [];
        $prepared = [];
        foreach ($entries as [$key, $value, $locale]) {
            $definition = $this->registry->definition($key);
            $storage = $this->storageLocale($definition, $locale);
            $native = $value === null ? null : $definition->normalize($value);
            $prepared[] = [$definition, $storage, $native];
            if (! $definition->translatable) {
                $pending[$key] = $native ?? $definition->default();
            }
        }

        $read = fn (string $key): mixed => array_key_exists($key, $pending) ? $pending[$key] : $this->get($key);
        foreach ($prepared as [$definition, , $native]) {
            if ($native !== null && $definition->check !== null && ($error = ($definition->check)($native, $read)) !== null) {
                throw new InvalidSettingValue($definition->key, $definition->secret ? 'does not meet the rules' : $error);
            }
        }

        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as [$definition, $storage, $native]) {
                $row = ['key' => $definition->key, 'locale' => $storage];
                if ($native === null) {
                    DB::table('settings')->where($row)->delete();

                    continue;
                }
                $payload = $definition->secret ? Crypt::encryptString((string) json_encode($native)) : $native;
                DB::table('settings')->upsert(
                    [$row + ['group' => $definition->group, 'value' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]],
                    ['key', 'locale'],
                    ['group', 'value', 'updated_at'],
                );
            }
        });

        $this->flush();
    }

    private function storageLocale(SettingDefinition $definition, ?string $locale): string
    {
        if (! $definition->translatable) {
            return $locale === null ? self::NO_LOCALE : throw new InvalidArgumentException("Setting [{$definition->key}] is not translatable.");
        }

        return $locale ?? app()->getLocale();
    }

    /** @return list<string> */
    private function localesToTry(SettingDefinition $definition, ?string $locale): array
    {
        if (! $definition->translatable) {
            return [$this->storageLocale($definition, $locale)];
        }

        return array_values(array_unique([$locale ?? app()->getLocale(), app(Locales::class)->default()]));
    }

    private function decode(SettingDefinition $definition, mixed $stored): mixed
    {
        if (! $definition->secret) {
            return $stored;
        }
        try {
            return json_decode(Crypt::decryptString((string) $stored), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return $definition->default(); // wrong APP_KEY: behave as unset, leak nothing
        }
    }

    /** @return array{values: array<string, array<string, mixed>>, locks: list<string>} */
    private function data(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            $cached = null;
        }
        if (is_array($cached) && isset($cached['values'], $cached['locks'])) {
            return $this->memo = $cached;
        }

        try {
            if (! Schema::hasTable('settings') || ! Schema::hasTable('settings_locks')) {
                return ['values' => [], 'locks' => []]; // install time: defaults, nothing cached
            }

            $values = [];
            foreach (DB::table('settings')->get(['key', 'locale', 'value']) as $row) {
                $values[$row->key][$row->locale] = json_decode((string) $row->value, true);
            }
            $data = ['values' => $values, 'locks' => DB::table('settings_locks')->pluck('key')->all()];
        } catch (Throwable) {
            return ['values' => [], 'locks' => []];
        }

        try {
            Cache::forever(self::CACHE_KEY, $data);
        } catch (Throwable) {
            // cache store not ready; serve from the database this time
        }

        return $this->memo = $data;
    }
}
