<?php

namespace Codeart\Core\Mail;

use Codeart\Core\Settings\Settings;
use Throwable;

/**
 * Applies the `mail.*` settings to the mail config at runtime. Only values that were actually stored
 * override the app's own config (.env); with nothing stored the app behaves as configured.
 * Safe when the database is not ready (install time): it then does nothing.
 */
class MailConfig
{
    public function __construct(private readonly Settings $settings) {}

    public function apply(): void
    {
        try {
            $this->applyStored();
        } catch (Throwable) {
            // database or cache not ready; keep the app's own mail config
        }
    }

    private function applyStored(): void
    {
        $stored = fn (string $key): bool => $this->settings->has($key);
        $get = fn (string $key): mixed => $this->settings->get($key);

        if ($stored('mail.from_address')) {
            config(['mail.from.address' => $get('mail.from_address')]);
        }
        if ($stored('mail.from_name')) {
            config(['mail.from.name' => $get('mail.from_name')]);
        }
        if ($stored('mail.driver')) {
            config(['mail.default' => $get('mail.driver')]);
        }
        foreach (['host', 'port', 'username', 'password'] as $field) {
            if ($stored('mail.'.$field)) {
                config(['mail.mailers.smtp.'.$field => $get('mail.'.$field)]);
            }
        }
        if ($stored('mail.encryption')) {
            // ssl = implicit TLS (smtps); tls = STARTTLS; none = plain
            config(['mail.mailers.smtp.scheme' => $get('mail.encryption') === 'ssl' ? 'smtps' : 'smtp']);
            config(['mail.mailers.smtp.encryption' => $get('mail.encryption') === 'none' ? null : $get('mail.encryption')]);
        }
        // Mailers are built once; make the next send use the new values.
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->purge();
        }
    }
}
