<?php

namespace Codeart\Core\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Answers 404 unless the app environment is `local` (developer-only pages). Runs before authentication. */
class LocalEnvironmentOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local'), 404);

        return $next($request);
    }
}
