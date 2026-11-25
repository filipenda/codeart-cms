<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Settings\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What Fortify's view hooks render (see AuthServiceProvider::registerScreens). Views live in `codeart::auth.*`
 * and can be overridden by an app (resources/views/vendor/codeart/auth/...).
 */
class Screens
{
    public function __construct(private readonly Settings $settings) {}

    public function login(Request $request): View
    {
        return $this->loginPage($request, 'login');
    }

    /** The registration tab of the sign-in page. A closed registration does not exist (404), like its POST route. */
    public function register(Request $request): View
    {
        abort_unless($this->registrationEnabled(), 404);

        return $this->loginPage($request, 'register');
    }

    public function twoFactorChallenge(Request $request): View
    {
        return view('codeart::auth.two-factor-challenge', ['recovery' => $request->boolean('recovery') || old('recovery_code') !== null]);
    }

    public function forgotPassword(): View
    {
        return view('codeart::auth.forgot-password');
    }

    public function resetPassword(Request $request): View
    {
        return view('codeart::auth.reset-password', [
            'token' => (string) $request->route('token'),
            'email' => (string) $request->query('email', old('email', '')),
        ]);
    }

    public function verifyEmail(): View
    {
        return view('codeart::auth.verify-email');
    }

    public function confirmPassword(): View
    {
        return view('codeart::auth.confirm-password');
    }

    public function registrationEnabled(): bool
    {
        return (bool) $this->settings->get('auth.registration.enabled');
    }

    private function loginPage(Request $request, string $tab): View
    {
        $registration = $this->registrationEnabled();
        // After a failed registration the browser comes back to this page with the old input: stay on that tab.
        $active = $registration && ($tab === 'register' || old('form') === 'register') ? 'register' : 'login';

        return view('codeart::auth.login', ['registration' => $registration, 'active' => $active, 'guard' => Auth::getDefaultDriver()]);
    }
}
