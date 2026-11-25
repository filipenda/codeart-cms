<?php

namespace Codeart\Core\Settings;

use Closure;

/** Definitions owned by core: appearance, content locales and security. */
final class CoreSettings
{
    public const string PERMISSION_APPEARANCE = 'settings.appearance';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [...self::appearance(), ...self::locales(), ...self::security()];
    }

    /** @return list<SettingDefinition> */
    private static function appearance(): array
    {
        $cfg = fn (string $key): Closure => fn (): mixed => config('codeart.theme.'.$key);
        $perm = self::PERMISSION_APPEARANCE;

        return [
            new SettingDefinition('theme.primary', SettingType::Color, $cfg('primary'), permission: $perm, label: 'Primary color', description: 'Main brand color; hover, link and text colors are derived from it.'),
            new SettingDefinition('theme.secondary', SettingType::Color, $cfg('secondary'), permission: $perm, label: 'Secondary color', description: 'Accent color.'),
            new SettingDefinition('theme.mode', SettingType::Enum, $cfg('mode'), permission: $perm, options: ['light', 'dark', 'auto'], label: 'Color mode', description: 'Light, dark, or follow the visitor\'s system preference.'),
            new SettingDefinition('theme.logo_light', SettingType::Image, $cfg('logo_light'), permission: $perm, label: 'Logo (light background)', description: 'PNG, JPG or WebP.'),
            new SettingDefinition('theme.logo_dark', SettingType::Image, $cfg('logo_dark'), permission: $perm, label: 'Logo (dark background)', description: 'PNG, JPG or WebP.'),
            new SettingDefinition('theme.favicon', SettingType::Image, $cfg('favicon'), permission: $perm, label: 'Favicon', description: 'PNG, JPG or WebP.'),
        ];
    }

    /** @return list<SettingDefinition> */
    private static function locales(): array
    {
        $cfg = fn (string $key): Closure => fn (): mixed => config('codeart.locales.'.$key);
        $known = fn (): array => array_keys((array) config('codeart.locales.available', []));
        $perm = self::PERMISSION_APPEARANCE;

        return [
            new SettingDefinition(
                'locales.default', SettingType::String, $cfg('default'), permission: $perm,
                label: 'Default content language', description: 'Source language of content; must be one of the enabled languages.',
                check: fn (mixed $value, Closure $read): ?string => in_array($value, (array) $read('locales.enabled'), true)
                    ? null : 'the default language must be one of the enabled languages',
            ),
            new SettingDefinition(
                'locales.enabled', SettingType::Json, $cfg('enabled'), itemRules: ['string'], permission: $perm,
                label: 'Enabled content languages', description: 'Languages content can be written in.',
                check: function (mixed $value, Closure $read) use ($known): ?string {
                    $enabled = (array) $value;
                    if (! array_is_list($enabled) || $enabled === [] || array_diff($enabled, $known()) !== []) {
                        return 'must be a non-empty list of known languages';
                    }
                    if (! in_array($read('locales.default'), $enabled, true)) {
                        return 'the default language must stay enabled';
                    }
                    foreach ($enabled as $locale) {
                        $source = config("codeart.locales.available.{$locale}.derived_from");
                        if (is_string($source) && ! in_array($source, $enabled, true)) {
                            return "{$locale} is generated from {$source}, so {$source} must be enabled too";
                        }
                    }

                    return null;
                },
            ),
            new SettingDefinition(
                'locales.fallbacks', SettingType::Json, $cfg('fallbacks'), permission: $perm,
                label: 'Language fallbacks', description: 'Which languages to try when a translation is missing, per language.',
                check: function (mixed $value) use ($known): ?string {
                    foreach ((array) $value as $locale => $chain) {
                        if (! in_array($locale, $known(), true) || ! is_array($chain) || array_diff($chain, $known()) !== []) {
                            return 'must map known languages to lists of known languages';
                        }
                    }

                    return null;
                },
            ),
        ];
    }

    /** @return list<SettingDefinition> security settings: defined and validated, not enforced yet (login comes later) */
    private static function security(): array
    {
        $int = fn (string $key, int $default, int $min, int $max, string $label, string $description): SettingDefinition => new SettingDefinition(
            $key, SettingType::Int, $default, ['integer', "min:{$min}", "max:{$max}"], label: $label, description: $description,
        );
        $flag = fn (string $key, bool $default, string $label): SettingDefinition => new SettingDefinition($key, SettingType::Bool, $default, label: $label);

        return [
            new SettingDefinition('security.2fa.mode', SettingType::Enum, 'off', options: ['off', 'optional', 'required'], label: 'Two-factor authentication', description: 'Off, optional for everyone, or required for the roles below.'),
            new SettingDefinition('security.2fa.required_roles', SettingType::Json, ['super-admin', 'admin'], itemRules: ['string', 'max:64'], label: 'Roles that must use 2FA', description: 'Applies when two-factor authentication is required.'),
            $int('security.password.min_length', 10, 8, 128, 'Minimum password length', 'At least 8 characters.'),
            $flag('security.password.require_mixed_case', true, 'Require upper and lower case letters'),
            $flag('security.password.require_number', true, 'Require a number'),
            $flag('security.password.require_symbol', false, 'Require a symbol'),
            $int('security.session.lifetime_minutes', 120, 5, 43200, 'Session lifetime (minutes)', 'Idle time before a user is signed out.'),
            $int('security.login.max_attempts', 5, 1, 100, 'Maximum login attempts', 'Failed attempts before a temporary lockout.'),
            $int('security.login.decay_minutes', 15, 1, 1440, 'Login lockout (minutes)', 'How long a lockout lasts.'),
            $int('security.activity_log.retention_days', 90, 7, 3650, 'Activity log retention (days)', 'Older entries are removed.'),
        ];
    }
}
