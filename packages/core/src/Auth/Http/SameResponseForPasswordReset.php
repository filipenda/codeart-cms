<?php

namespace Codeart\Core\Auth\Http;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Send me a reset link" answers the same whether or not the address exists (or was throttled),
 * and a failed reset says "invalid or expired" whether the address or the token was wrong.
 */
class SameResponseForPasswordReset implements FailedPasswordResetLinkRequestResponse, FailedPasswordResetResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected ?string $status = null) {}

    public function toResponse($request): Response
    {
        $isLinkRequest = $request->routeIs('password.email');
        $message = __($isLinkRequest ? 'codeart::auth.reset_sent' : 'codeart::auth.reset_failed');

        if ($isLinkRequest) {
            return $request->wantsJson() ? new JsonResponse(['message' => $message], 200) : back()->with('status', $message);
        }

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message, 'errors' => ['email' => [$message]]], 422)
            : back()->withErrors(['email' => $message]);
    }
}
