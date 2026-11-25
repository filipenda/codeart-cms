<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Actions\AcceptInvitation;
use Codeart\Core\Auth\InvalidInvitation;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed invitation link. Both routes sit behind a signature check, so a changed token or expiry never reaches
 * this class. Browsers get the "set your password" screen (or a clear expired / used / invalid state, 410);
 * JSON clients get the same facts as JSON.
 */
class InvitationController
{
    /** Unknown, used and expired tokens all answer 410. */
    public function show(Request $request, string $token): JsonResponse|View|Response
    {
        $invitation = $this->find($token);
        $usable = $invitation !== null && $invitation->isUsable();

        if ($request->expectsJson()) {
            return $usable
                ? response()->json(['name' => $invitation->name, 'email' => $invitation->email])
                : response()->json(['message' => __('codeart::auth.invitation_invalid')], 410);
        }

        return response()->view('codeart::auth.invitation', [
            'state' => $invitation?->state() ?? 'invalid',
            'invitation' => $usable ? $invitation : null,
            'token' => $token,
        ], $usable ? 200 : 410);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept, Locales $locales): JsonResponse|RedirectResponse|Response
    {
        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
            'locale' => ['nullable', 'string'],
        ], [], __('codeart::auth.attributes'));

        $locale = $request->input('locale') ?? ($request->hasSession() ? $request->session()->get('codeart.locale') : null);
        try {
            $accept($token, (string) $request->input('password'), is_string($locale) && $locales->isEnabled($locale) ? $locale : null);
        } catch (InvalidInvitation $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 410);
            }

            return response()->view('codeart::auth.invitation', [
                'state' => $this->find($token)?->state() ?? 'invalid',
                'invitation' => null,
                'token' => $token,
            ], 410);
        }

        return $request->expectsJson()
            ? response()->json(['accepted' => true])
            : redirect()->route('login')->with('status', 'invitation-accepted');
    }

    private function find(string $token): ?UserInvitation
    {
        return UserInvitation::query()->where('token_hash', UserInvitation::hashToken($token))->first();
    }
}
