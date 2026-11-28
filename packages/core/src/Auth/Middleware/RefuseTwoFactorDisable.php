<?php

namespace Codeart\Core\Auth\Middleware;

use Closure;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\TwoFactorPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Switching 2FA off is refused (403) while the security settings require it for the user's role. */
final class RefuseTwoFactorDisable
{
    public function __construct(private readonly TwoFactorPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->policy->requiredFor($request->user(Admin::guardName()))) {
            $message = __('codeart::profile.two_factor_required');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'reason' => 'two_factor_required'], 403);
            }
            abort(403, $message);
        }

        return $next($request);
    }
}
