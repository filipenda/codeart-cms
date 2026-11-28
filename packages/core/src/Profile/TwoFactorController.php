<?php

namespace Codeart\Core\Profile;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Auth\TwoFactorQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * The "2FA" tab of Moj profil. Fortify keeps doing the work (enable, confirm: its own routes); this adds the screen and
 * two guarded actions that need the server confirmation AND a fresh password: new recovery codes, and turning 2FA off
 * (refused with 403 while the settings require 2FA for the user's role). Events of Fortify's actions reach the activity log.
 *
 * States: off (guide + start button), pending (guide + QR + key + code form), on (status, recovery codes, new codes, turn off).
 */
final class TwoFactorController
{
    private const array SHOW_CODES_AFTER = ['two-factor-authentication-confirmed', 'recovery-codes-generated'];

    public function show(Request $request, TwoFactorPolicy $policy): View|RedirectResponse
    {
        $user = Admin::staff($this->me($request));
        $enabled = $user->hasTwoFactorEnabled();
        $pending = ! $enabled && $user->getAttribute('two_factor_secret') !== null;
        $data = ['available' => $policy->available() || $enabled, 'required' => $policy->requiredFor($user), 'enabled' => $enabled, 'pending' => $pending, 'codes' => [], 'qr' => null, 'secret' => null, 'confirmErrors' => new MessageBag];

        if ($pending) {
            $data['qr'] = TwoFactorQrCode::withQuietZone($user->twoFactorQrCodeSvg());
            $data['secret'] = decrypt($user->getAttribute('two_factor_secret'));
            $data['confirmErrors'] = $request->session()->get('errors')?->getBag('confirmTwoFactorAuthentication') ?? new MessageBag;
        }

        if ($enabled) {
            $status = $request->session()->get('status');
            $fresh = is_string($status) && in_array($status, self::SHOW_CODES_AFTER, true);
            if ($request->boolean('codes') && ! $fresh) {
                if (! $this->passwordRecentlyConfirmed($request)) {
                    return redirect()->guest(route('password.confirm'));
                }
                $fresh = true;
            }
            $data['codes'] = $fresh ? array_values(array_filter((array) json_decode(decrypt($user->getAttribute('two_factor_recovery_codes')), true), 'is_string')) : [];
        }

        return view('codeart::admin.profile.two-factor', $data + $this->shell($request));
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $user = Admin::staff($this->me($request));
        abort_unless($user->hasTwoFactorEnabled(), 404);
        $generate($user);

        return redirect()->route('admin.profile.two-factor')->with('status', 'recovery-codes-generated');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable, TwoFactorPolicy $policy): RedirectResponse
    {
        $user = $this->me($request);
        abort_if($policy->requiredFor($user), 403, __('codeart::profile.two_factor_required'));
        $disable($user);

        return redirect()->route('admin.profile.two-factor')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.tf_disabled')]);
    }

    /** @return array<string, mixed> */
    private function shell(Request $request): array
    {
        $user = $this->me($request);
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        usort($roles, fn ($a, $b) => RoleRank::of($b) <=> RoleRank::of($a));

        return ['me' => $user, 'role' => $roles[0] ?? null, 'avatarUrl' => Avatar::url($user)];
    }

    private function me(Request $request): Model
    {
        $user = $request->user(Admin::guardName());

        return $user instanceof Model ? $user : throw new \RuntimeException('No signed-in user.');
    }

    private function passwordRecentlyConfirmed(Request $request): bool
    {
        return time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}
