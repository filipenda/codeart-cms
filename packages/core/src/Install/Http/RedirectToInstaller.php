<?php

namespace Codeart\Core\Install\Http;

use Closure;
use Codeart\Core\Install\InstallState;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While the site is not installed every page leads to the wizard. */
class RedirectToInstaller
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->pending() && ! $request->is('install', 'install/*', 'up')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
