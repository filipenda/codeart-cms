<?php

namespace Codeart\Core\Install\Http;

use Closure;
use Codeart\Core\Install\InstallState;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Once installed the wizard no longer exists: 404. Sets the language of the wizard (?lang=sr|sr-Cyrl|en, kept in the session). */
class EnsureNotInstalled
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->installed(), 404);

        $available = array_keys((array) config('codeart.locales.available', []));
        $lang = $request->query('lang');
        if (is_string($lang) && in_array($lang, $available, true)) {
            $request->session()->put('installer.locale', $lang);
        }
        app()->setLocale((string) $request->session()->get('installer.locale', 'sr'));

        return $next($request);
    }
}
