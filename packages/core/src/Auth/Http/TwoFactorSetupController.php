<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Auth\TwoFactorQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

/**
 * Two-factor setup screen (route `admin.two-factor.setup`). Four states, all server-rendered:
 *  off      two-factor is switched off in the settings and not set up
 *  start    nothing yet: a button posts to Fortify (needs a recent password confirmation, asked for first)
 *  confirm  secret exists: inline SVG QR code, the manual key, a form for the first code
 *  done     confirmed; the recovery codes are shown ONLY right after the code was accepted (or after regenerating)
 */
class TwoFactorSetupController
{
    private const array SHOW_CODES_AFTER = ['two-factor-authentication-confirmed', 'recovery-codes-generated'];

    public function __invoke(Request $request, TwoFactorPolicy $policy): View|RedirectResponse
    {
        $user = Admin::guard()->user();
        abort_if($user === null, 401);

        $enabled = $user->hasTwoFactorEnabled();
        $pending = ! $enabled && $user->two_factor_secret !== null;

        if (! $enabled && ! $policy->available()) {
            return view('codeart::auth.two-factor-setup', ['state' => 'off']);
        }

        if (! $enabled && ! $pending) {
            if (! $this->passwordRecentlyConfirmed($request)) {
                return redirect()->guest(route('password.confirm'));
            }

            return view('codeart::auth.two-factor-setup', ['state' => 'start']);
        }

        if ($pending) {
            return view('codeart::auth.two-factor-setup', [
                'state' => 'confirm',
                'qr' => TwoFactorQrCode::withQuietZone($user->twoFactorQrCodeSvg()),
                'secret' => decrypt($user->two_factor_secret),
                'errors' => $request->session()->get('errors')?->getBag('confirmTwoFactorAuthentication') ?? new MessageBag,
            ]);
        }

        $status = $request->session()->get('status');
        $codes = is_string($status) && in_array($status, self::SHOW_CODES_AFTER, true)
            ? array_values((array) json_decode(decrypt($user->two_factor_recovery_codes), true))
            : [];

        return view('codeart::auth.two-factor-setup', ['state' => 'done', 'codes' => $codes]);
    }

    private function passwordRecentlyConfirmed(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return time() - $confirmedAt < (int) config('auth.password_timeout', 10800);
    }
}
