<?php

namespace Codeart\Core\Auth\Middleware;

use Closure;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\BotProtection;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every Fortify route (sign-in, registration, password reset, two-factor):
 *  - applies the remember-me duration from the settings
 *  - registration: closed (404) unless enabled; honeypot + minimum time; throttled per IP
 *  - two-factor setup: refused when two-factor is turned off
 */
class ConfigureAuthRequest
{
    public function __construct(
        private readonly Settings $settings,
        private readonly BotProtection $bots,
        private readonly TwoFactorPolicy $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Admin::guard()->setRememberDuration((int) $this->settings->get('security.login.remember_days') * 1440); /** @phpstan-ignore method.notFound */
        $name = (string) $request->route()?->getName();

        if ($name === 'register.store') {
            return $this->registration($request, $next);
        }
        if ($name === 'two-factor.enable' && ! $this->twoFactor->available()) {
            abort(403, __('codeart::auth.two_factor_off'));
        }

        return $next($request);
    }

    private function registration(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('auth.registration.enabled')) {
            abort(404);
        }

        $key = 'registration|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429);
        }
        RateLimiter::hit($key, 3600);

        // Bots learn nothing: the same message as any other failed registration.
        if (! $this->bots->passes($request)) {
            throw ValidationException::withMessages(['email' => [__('codeart::auth.registration_failed')]]);
        }

        return $next($request);
    }
}
