<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Actions\AcceptInvitation;
use Codeart\Core\Auth\InvalidInvitation;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Translations\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed invitation link. Both routes sit behind `signed`, so a changed token or expiry is a 403
 * before anything here runs. The screens task renders the form on `show` (or replaces this controller).
 */
class InvitationController
{
    /** Tells the screen whether the link is still good, and for whom. Unknown, used and expired look the same (410). */
    public function show(string $token): JsonResponse
    {
        $invitation = UserInvitation::query()->where('token_hash', UserInvitation::hashToken($token))->first();
        if ($invitation === null || ! $invitation->isUsable()) {
            return response()->json(['message' => __('codeart::auth.invitation_invalid')], 410);
        }

        return response()->json(['name' => $invitation->name, 'email' => $invitation->email]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept, Locales $locales): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
            'locale' => ['nullable', 'string'],
        ], [], __('codeart::auth.attributes'));

        $locale = $request->input('locale');
        try {
            $accept($token, (string) $request->input('password'), is_string($locale) && $locales->isEnabled($locale) ? $locale : null);
        } catch (InvalidInvitation $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        }

        return response()->json(['accepted' => true]);
    }
}
