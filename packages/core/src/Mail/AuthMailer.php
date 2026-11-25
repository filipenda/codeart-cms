<?php

namespace Codeart\Core\Mail;

use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the system mails in the recipient's language.
 *
 * `deferred` sends after the HTTP response (no queue worker needed): a fast answer for sign-in related
 * mails, which also keeps "does this e-mail exist?" from showing in the response time. A failed deferred
 * mail is reported, not thrown. Outside a routed request (commands, tests of commands) it sends at once.
 */
class AuthMailer
{
    public function __construct(private readonly Locales $locales) {}

    public function send(object|string $to, Mailable $mail, bool $deferred = false): void
    {
        $locale = is_object($to) && method_exists($to, 'preferredLocale') ? $to->preferredLocale() : null;
        $locale = is_string($locale) && $this->locales->isEnabled($locale) ? $locale : app()->getLocale();
        $send = fn () => Mail::to($to)->locale($locale)->send($mail);

        if (! $deferred || request()->route() === null) {
            $send();

            return;
        }

        $sent = false; // terminating callbacks of a long-lived process (tests, Octane) run on every later request too
        app()->terminating(function () use ($send, &$sent): void {
            if ($sent) {
                return;
            }
            $sent = true;
            try {
                $send();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
