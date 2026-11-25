<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\AdminAccess;
use Codeart\Core\Auth\TwoFactorPolicy;
use Illuminate\Http\JsonResponse;

/** What the screens need to know about the signed-in user (also how they learn why access is denied). */
class SessionStateController
{
    public function __invoke(AdminAccess $access, TwoFactorPolicy $twoFactor): JsonResponse
    {
        $user = Admin::guard()->user();
        abort_if($user === null, 401);

        return response()->json([
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'theme_preference' => $user->theme_preference,
                'roles' => $user->getRoleNames(),
            ],
            'can_access_admin' => ($denial = $access->denial($user)) === null,
            'denial' => $denial,
            'two_factor' => [
                'available' => $twoFactor->available(),
                'enabled' => $user->hasTwoFactorEnabled(),
                'must_set_up' => $user->mustSetUpTwoFactor(),
            ],
        ]);
    }
}
