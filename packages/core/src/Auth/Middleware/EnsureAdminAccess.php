<?php

namespace Codeart\Core\Auth\Middleware;

use Closure;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\AdminAccess;
use Codeart\Core\Support\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin.access`: signed in on the admin guard, active, approved and verified when those are required,
 * allowed to use the admin (permission admin.access) and, when two-factor is required for the role,
 * set up for it (until then only the two-factor routes and logout are reachable). It also applies the user's display time zone.
 */
class EnsureAdminAccess
{
    /** @var array<string, string> reason => screen route */
    private const array SCREENS = [
        AdminAccess::UNVERIFIED => 'verification.notice',
        AdminAccess::TWO_FACTOR => 'admin.two-factor.setup',
    ];

    public function __construct(private readonly AdminAccess $access, private readonly DisplayTime $time) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Admin::guard()->user();
        if ($user === null) {
            abort(401);
        }

        // Every admin route passes here, so this is where the display time zone of the signed-in user is applied (their
        // `timezone`, else the site zone). Dates stay UTC in the database; see DisplayTime.
        $zone = $user->getAttribute('timezone');
        $this->time->use(is_string($zone) && $zone !== '' ? $zone : null);

        $reason = $this->access->denial($user);
        if ($reason === null || $this->exempt($request, $reason)) {
            return $next($request);
        }

        $screen = self::SCREENS[$reason] ?? null;
        if ($screen !== null && ! $request->expectsJson() && Route::has($screen)) {
            return redirect()->route($screen);
        }

        $message = __('codeart::auth.denied.'.$reason);
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reason' => $reason], 403);
        }

        // Browsers get the themed "no access" page (when the screens are on); anything else the plain 403.
        if (config('codeart.auth.views', true)) {
            return response()->view('codeart::auth.no-access', ['reason' => $reason, 'message' => $message], 403);
        }
        abort(403, $message);
    }

    /** While two-factor setup is pending, the setup routes and logout stay reachable. */
    private function exempt(Request $request, string $reason): bool
    {
        if ($reason !== AdminAccess::TWO_FACTOR) {
            return false;
        }
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'two-factor.')
            || in_array($name, (array) config('codeart.auth.two_factor_exempt_routes', []), true);
    }
}
