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
        return [...self::appearance(), ...self::locales(), ...self::security(), ...self::auth(), ...self::mail(), ...self::profile()];
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
            new SettingDefinition('theme.logo_light', SettingType::Image, $cfg('logo_light'), permission: $perm, label: 'Logo (light background)', description: 'Best: PNG or WebP with a transparent background (empty margins are trimmed automatically; the original is kept). JPG also works.'),
            new SettingDefinition('theme.logo_dark', SettingType::Image, $cfg('logo_dark'), permission: $perm, label: 'Logo (dark background)', description: 'Best: PNG or WebP with a transparent background (empty margins are trimmed automatically; the original is kept). JPG also works.'),
            new SettingDefinition('theme.favicon', SettingType::Image, $cfg('favicon'), permission: $perm, label: 'Favicon', description: 'Best: PNG or WebP with a transparent background. JPG also works.'),
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

    /** @return list<SettingDefinition> */
    private static function profile(): array
    {
        return [
            new SettingDefinition(
                'site.timezone', SettingType::String, 'Europe/Belgrade', ['required', 'string'],
                label: 'Site time zone', description: 'Time zone dates are shown in unless a user chose their own. The database always keeps UTC.',
                check: fn (mixed $value): ?string => is_string($value) && in_array($value, \DateTimeZone::listIdentifiers(), true) ? null : 'must be a valid time zone identifier, e.g. Europe/Belgrade',
            ),
            new SettingDefinition('profile.avatar_max_kb', SettingType::Int, 5120, ['integer', 'min:100', 'max:20480'], label: 'Maximum avatar size (KB)', description: 'Largest profile picture people may upload (default 5 MB).'),
        ];
    }

    /** @return list<SettingDefinition> */
    private static function security(): array
    {
        $int = fn (string $key, int $default, int $min, int $max, string $label, string $description): SettingDefinition => new SettingDefinition(
            $key, SettingType::Int, $default, ['integer', "min:{$min}", "max:{$max}"], label: $label, description: $description,
        );
        $flag = fn (string $key, bool $default, string $label): SettingDefinition => new SettingDefinition($key, SettingType::Bool, $default, label: $label);

        return [
            new SettingDefinition('security.2fa.mode', SettingType::Enum, 'required', options: ['off', 'optional', 'required'], label: 'Two-factor authentication', description: 'Off, optional for everyone, or required for the roles below (default: required).'),
            new SettingDefinition('security.2fa.required_roles', SettingType::Json, ['super-admin', 'admin'], itemRules: ['string', 'max:64'], label: 'Roles that must use 2FA', description: 'Applies when two-factor authentication is required.'),
            $int('security.password.min_length', 10, 8, 128, 'Minimum password length', 'At least 8 characters.'),
            $flag('security.password.require_mixed_case', true, 'Require upper and lower case letters'),
            $flag('security.password.require_number', true, 'Require a number'),
            $flag('security.password.require_symbol', false, 'Require a symbol'),
            $flag('security.password.check_breached', true, 'Reject passwords found in known data leaks'),
            $int('security.session.lifetime_minutes', 120, 5, 43200, 'Session lifetime (minutes)', 'Idle time before a user is signed out.'),
            $int('security.login.max_attempts', 5, 1, 100, 'Maximum login attempts', 'Failed attempts before a temporary lockout.'),
            $int('security.login.decay_minutes', 15, 1, 1440, 'Login lockout (minutes)', 'How long a lockout lasts.'),
            $int('security.login.remember_days', 30, 1, 365, 'Remember me (days)', 'How long a "remember me" login lasts.'),
            $int('security.activity_log.retention_days', 90, 7, 3650, 'Activity log retention (days)', 'Older entries are removed.'),
        ];
    }

    /** @return list<SettingDefinition> access to the admin: path, invitations and public registration (super-admin only) */
    private static function auth(): array
    {
        $flag = fn (string $key, bool $default, string $label, string $description = ''): SettingDefinition => new SettingDefinition($key, SettingType::Bool, $default, label: $label, description: $description);

        return [
            new SettingDefinition(
                'auth.admin_path', SettingType::String, 'admin',
                rules: ['required', 'not_in:api,storage,vendor,livewire,up,build,assets,css,js,images,img,fonts,telescope,horizon,pulse,nova', 'regex:/^[a-z0-9][a-z0-9\/_-]{0,63}$/', 'not_regex:/(^|\/)(\.\.?)(\/|$)/', 'not_regex:/\/\//'],
                label: 'Admin path', description: 'URL prefix of the admin area and every sign-in route (default: admin). Lowercase letters, digits, - and _.',
            ),
            new SettingDefinition('auth.invitation.expiry_days', SettingType::Int, 7, ['integer', 'min:1', 'max:60'], label: 'Invitation expiry (days)', description: 'How long an invitation link works.'),
            $flag('auth.registration.enabled', false, 'Public registration', 'Let visitors register themselves. Off by default.'),
            $flag('auth.registration.require_email_verification', true, 'Require e-mail verification'),
            $flag('auth.registration.require_approval', true, 'Require approval of new registrations'),
            new SettingDefinition('auth.registration.default_role', SettingType::String, 'member', ['max:64', 'not_in:super-admin,admin'], label: 'Role of new registrations'),
            new SettingDefinition('auth.registration.allowed_email_domains', SettingType::Json, [], itemRules: ['string', 'max:190', 'regex:/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/'], label: 'Allowed e-mail domains', description: 'Empty list = any domain.'),
            new SettingDefinition('auth.registration.min_seconds', SettingType::Int, 3, ['integer', 'min:0', 'max:600'], label: 'Minimum time to fill the form (seconds)', description: 'Faster submissions are treated as bots.'),
        ];
    }

    /** @return list<SettingDefinition> outgoing e-mail; stored values override the app's mail config at runtime */
    private static function mail(): array
    {
        $cfg = fn (string $key, mixed $fallback = null): Closure => fn (): mixed => config($key, $fallback);
        $str = fn (string $key, Closure $default, string $label, array $rules = []): SettingDefinition => new SettingDefinition($key, SettingType::String, $default, $rules, label: $label);

        return [
            $str('mail.from_name', $cfg('mail.from.name', ''), 'Sender name', ['max:190']),
            $str('mail.from_address', $cfg('mail.from.address', ''), 'Sender address', ['email', 'max:190']),
            new SettingDefinition('mail.driver', SettingType::Enum, fn (): string => config('mail.default') === 'smtp' ? 'smtp' : 'log', options: ['smtp', 'log'], label: 'Mail driver', description: 'smtp sends real e-mail; log only writes it to the log.'),
            $str('mail.host', $cfg('mail.mailers.smtp.host', ''), 'SMTP host', ['max:190']),
            new SettingDefinition('mail.port', SettingType::Int, fn (): int => (int) config('mail.mailers.smtp.port', 587), ['integer', 'min:1', 'max:65535'], label: 'SMTP port'),
            $str('mail.username', $cfg('mail.mailers.smtp.username', ''), 'SMTP username', ['max:190']),
            new SettingDefinition('mail.password', SettingType::Secret, null, ['max:500'], label: 'SMTP password'),
            new SettingDefinition('mail.encryption', SettingType::Enum, 'tls', options: ['tls', 'ssl', 'none'], label: 'Encryption', description: 'tls = STARTTLS (port 587), ssl = implicit TLS (port 465).'),
        ];
    }
}
