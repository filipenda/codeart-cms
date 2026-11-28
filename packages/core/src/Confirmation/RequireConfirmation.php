<?php

namespace Codeart\Core\Confirmation;

use Closure;
use Codeart\Core\Auth\Admin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `codeart.confirmed`: put it on every destructive admin route (delete, anonymize ...) that a table row action or a
 * `<x-codeart::confirm>` button posts to. The request must carry the `confirmation` token the confirmation step issued
 * for this user, method and path; one use only, 10 minutes. Without it nothing runs: the user is sent back with a message.
 */
final class RequireConfirmation
{
    public function __construct(private readonly ConfirmationTokens $tokens) {}

    public static function scope(string $method, string $path): string
    {
        return 'action:'.strtoupper($method).':'.'/'.trim($path, '/');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(Admin::guardName());
        if (! $this->tokens->consume($request->input('confirmation'), $user, self::scope($request->method(), $request->getPathInfo()))) {
            if ($request->expectsJson()) {
                abort(428, __('codeart::table.confirm_required'));
            }

            return back()->with('toast', ['type' => 'danger', 'message' => __('codeart::table.confirm_required')]);
        }

        return $next($request);
    }
}
