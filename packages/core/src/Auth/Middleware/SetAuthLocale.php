<?php

namespace Codeart\Core\Auth\Middleware;

use Closure;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Translations\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of the sign-in screens and the admin: the visitor's choice (session, then cookie), then the signed-in
 * user's saved language, then the default content locale. Only enabled locales count.
 */
class SetAuthLocale
{
    public const string SESSION_KEY = 'codeart.locale';

    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->cookie(self::cookieName()),
            Admin::guard()->user()->locale ?? null,
        ];

        $locale = $this->locales->default();
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->locales->isEnabled($candidate)) {
                $locale = $candidate;
                break;
            }
        }
        app()->setLocale($locale);

        return $next($request);
    }

    public static function cookieName(): string
    {
        return (string) config('codeart.auth.locale_cookie', 'codeart_locale');
    }
}
