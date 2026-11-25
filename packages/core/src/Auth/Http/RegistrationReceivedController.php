<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** "Registration received": shown right after registering, and to anyone still waiting for approval. */
class RegistrationReceivedController
{
    public function __invoke(): View|RedirectResponse
    {
        $user = Admin::guard()->user();
        abort_if($user === null, 401);

        if ($user->canAccessAdmin()) {
            return redirect(config('fortify.home', '/'));
        }

        return view('codeart::auth.registration-received', [
            'pendingApproval' => $user->isPendingApproval(),
            'needsVerification' => $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail(),
        ]);
    }
}
