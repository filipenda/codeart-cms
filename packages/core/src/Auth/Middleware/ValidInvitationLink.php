<?php

namespace Codeart\Core\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like Laravel's `signed`, but a browser gets the invitation screen with a clear "expired" or "invalid" state
 * instead of a bare 403 page. Scripts (JSON) still get the plain 403.
 */
class ValidInvitationLink
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasValidSignature()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            abort(403);
        }

        // A genuine link whose time ran out is "expired"; anything else (changed, unsigned) is "invalid".
        $state = URL::hasCorrectSignature($request) && ! URL::signatureHasNotExpired($request) ? 'expired' : 'invalid';

        return response()->view('codeart::auth.invitation', ['state' => $state, 'invitation' => null, 'token' => ''], 403);
    }
}
