<?php

namespace Codeart\Core\Profile;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Actions\UpdateUserPassword;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * "Moj profil": only ever the signed-in user's own row (there is no id in any URL). Whitelisted fields only: name,
 * locale, theme. Role, e-mail, status and everything else cannot be reached from here.
 */
final class ProfileController
{
    public function __construct(private readonly ActivityLog $log) {}

    public function show(Request $request, TwoFactorPolicy $twoFactor, Locales $locales): View
    {
        $user = $this->me($request);

        return view('codeart::admin.profile.show', [
            'me' => $user,
            'twoFactorAvailable' => $twoFactor->available(),
            'twoFactorRequired' => $twoFactor->requiredFor($user),
            'twoFactorOn' => Admin::staff($user)->hasTwoFactorEnabled(),
            'locales' => collect($locales->enabled())->mapWithKeys(fn (string $c): array => [$c => $locales->label($c)])->all(),
        ]);
    }

    public function update(Request $request, Locales $locales): RedirectResponse
    {
        $user = $this->me($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in($locales->enabled())],
            'theme_preference' => ['nullable', 'string', Rule::in(['light', 'dark', 'auto'])],
        ], [], __('codeart::auth.attributes'));

        $old = ['name' => $user->getAttribute('name'), 'locale' => $user->getAttribute('locale'), 'theme_preference' => $user->getAttribute('theme_preference')];
        $user->forceFill(['name' => $data['name'], 'locale' => $data['locale'] ?? null, 'theme_preference' => $data['theme_preference'] ?? null])->save();
        $new = ['name' => $user->getAttribute('name'), 'locale' => $user->getAttribute('locale'), 'theme_preference' => $user->getAttribute('theme_preference')];
        if ($old !== $new) {
            $this->log->record('profile.updated', 'Profile updated', $user, $user, ['old' => $old, 'new' => $new], 'users');
        }

        return redirect()->route('admin.profile')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.saved')]);
    }

    /** The theme switcher of the user menu saves here. */
    public function theme(Request $request): JsonResponse
    {
        $user = $this->me($request);
        $data = $request->validate(['theme' => ['required', 'string', Rule::in(['light', 'dark', 'auto'])]]);
        $user->forceFill(['theme_preference' => $data['theme']])->saveQuietly();

        return response()->json(['theme' => $data['theme']]);
    }

    public function password(Request $request, UpdateUserPassword $update): RedirectResponse
    {
        $user = $this->me($request);
        $update->update($user, $request->only(['current_password', 'password', 'password_confirmation']));
        $this->log->record('password.changed', 'Password changed', $user, $user, [], 'users');

        return redirect()->route('admin.profile')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.password_changed')]);
    }

    /** @return Model&Authenticatable */
    private function me(Request $request): Model
    {
        $user = $request->user(Admin::guardName());

        return $user instanceof Model ? $user : throw new RuntimeException('No signed-in user.');
    }
}
