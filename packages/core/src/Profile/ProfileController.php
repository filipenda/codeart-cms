<?php

namespace Codeart\Core\Profile;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Actions\UpdateUserPassword;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * "Moj profil": only ever the signed-in user's own row (there is no id in any URL). Whitelisted fields only: name,
 * phone, locale, theme, avatar. Role, e-mail, status and everything else cannot be reached from here.
 */
final class ProfileController
{
    public function __construct(private readonly ActivityLog $log) {}

    public function show(Request $request, Locales $locales, AvatarUploader $avatars): View
    {
        return view('codeart::admin.profile.show', $this->data($request) + [
            'locales' => collect($locales->enabled())->mapWithKeys(fn (string $c): array => [$c => $locales->label($c)])->all(),
            'avatarMaxMb' => round($avatars->maxKb() / 1024, 1),
        ]);
    }

    public function passwordPage(Request $request): View
    {
        return view('codeart::admin.profile.password', $this->data($request));
    }

    public function twoFactorPage(Request $request, TwoFactorPolicy $twoFactor): View
    {
        $user = $this->me($request);

        return view('codeart::admin.profile.two-factor', $this->data($request) + [
            'twoFactorAvailable' => $twoFactor->available(),
            'twoFactorRequired' => $twoFactor->requiredFor($user),
            'twoFactorOn' => Admin::staff($user)->hasTwoFactorEnabled(),
        ]);
    }

    public function update(Request $request, Locales $locales): RedirectResponse
    {
        $user = $this->me($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-()\s]+$/'],
            'locale' => ['nullable', 'string', Rule::in($locales->enabled())],
            'theme_preference' => ['nullable', 'string', Rule::in(['light', 'dark', 'auto'])],
        ], [], __('codeart::auth.attributes'));

        $fields = fn (): array => ['name' => $user->getAttribute('name'), 'phone' => $user->getAttribute('phone'), 'locale' => $user->getAttribute('locale'), 'theme_preference' => $user->getAttribute('theme_preference')];
        $old = $fields();
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';
        $user->forceFill(['name' => $data['name'], 'phone' => $phone === '' ? null : $phone, 'locale' => $data['locale'] ?? null, 'theme_preference' => $data['theme_preference'] ?? null])->save();
        $new = $fields();
        if ($old !== $new) {
            $this->log->record('profile.updated', 'Profile updated', $user, $user, ['old' => $old, 'new' => $new], 'users');
        }

        return redirect()->route('admin.profile')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.saved')]);
    }

    /** Only the signed-in user's own picture; there is no id in the URL. */
    public function avatar(Request $request, AvatarUploader $avatars): RedirectResponse
    {
        $user = $this->me($request);
        $request->validate(['avatar' => ['required', 'file']], [], ['avatar' => __('codeart::profile.avatar')]);
        $file = $request->file('avatar');
        if (is_array($file) || $file === null) {
            throw ValidationException::withMessages(['avatar' => __('codeart::profile.avatar_failed')]);
        }
        $avatars->store($user, $file);
        $this->log->record('profile.avatar_changed', 'Avatar changed', $user, $user, [], 'users');

        return redirect()->route('admin.profile')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.avatar_saved')]);
    }

    public function removeAvatar(Request $request, AvatarUploader $avatars): RedirectResponse
    {
        $user = $this->me($request);
        if ($user->getAttribute('avatar_path') !== null) {
            $avatars->remove($user);
            $this->log->record('profile.avatar_removed', 'Avatar removed', $user, $user, [], 'users');
        }

        return redirect()->route('admin.profile')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.avatar_removed')]);
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

        return redirect()->route('admin.profile.password.show')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.password_changed')]);
    }

    /**
     * What the profile card on every tab shows.
     *
     * @return array<string, mixed>
     */
    private function data(Request $request): array
    {
        $user = $this->me($request);
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        usort($roles, fn ($a, $b) => RoleRank::of($b) <=> RoleRank::of($a));

        return ['me' => $user, 'role' => $roles[0] ?? null, 'avatarUrl' => Avatar::url($user)];
    }

    /** @return Model&Authenticatable */
    private function me(Request $request): Model
    {
        $user = $request->user(Admin::guardName());

        return $user instanceof Model ? $user : throw new RuntimeException('No signed-in user.');
    }
}
