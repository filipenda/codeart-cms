<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Bot protection for public forms (no third-party service).
 *
 *  - honeypot: a field humans never see and never fill (hide it with CSS, tabindex="-1", autocomplete="off")
 *  - signed timestamp: a token issued when the form is rendered; a form submitted faster than
 *    auth.registration.min_seconds, with a forged or too old token, is a bot
 *
 * Screens: render `<input name="{{ $bot->honeypotField() }}" ...>` and
 * `<input type="hidden" name="{{ $bot->tokenField() }}" value="{{ $bot->issueToken() }}">`.
 * The check runs as middleware on the registration route (see docs/auth.md).
 */
class BotProtection
{
    private const int MAX_AGE_SECONDS = 86400;

    public function __construct(private readonly Settings $settings) {}

    public function honeypotField(): string
    {
        return 'company_website';
    }

    public function tokenField(): string
    {
        return 'form_token';
    }

    /** Value for the hidden token field: the render time, encrypted and authenticated with APP_KEY. */
    public function issueToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /** @return bool true = looks human */
    public function passes(Request $request): bool
    {
        if ((string) $request->input($this->honeypotField(), '') !== '') {
            return false;
        }

        try {
            $issuedAt = (int) Crypt::decryptString((string) $request->input($this->tokenField(), ''));
        } catch (Throwable) {
            return false;
        }

        $age = now()->getTimestamp() - $issuedAt;

        return $age >= (int) $this->settings->get('auth.registration.min_seconds') && $age <= self::MAX_AGE_SECONDS;
    }
}
