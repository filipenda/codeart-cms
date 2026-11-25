<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Settings\Settings;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter;

/** Fortify's login limiter (key: e-mail + IP) with security.login.max_attempts / decay_minutes from the settings. */
class ConfiguredLoginRateLimiter extends LoginRateLimiter
{
    public function __construct(RateLimiter $limiter, private readonly Settings $settings)
    {
        parent::__construct($limiter);
    }

    public function tooManyAttempts(Request $request)
    {
        return $this->limiter->tooManyAttempts($this->throttleKey($request), (int) $this->settings->get('security.login.max_attempts'));
    }

    public function increment(Request $request)
    {
        $this->limiter->hit($this->throttleKey($request), (int) $this->settings->get('security.login.decay_minutes') * 60);
    }
}
