<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\Middleware\SetAuthLocale;
use Codeart\Core\Translations\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The language switcher: only enabled locales are accepted; remembered in the session, a cookie and (signed in) the user. */
class LocaleController
{
    public function __invoke(Request $request, Locales $locales): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in($locales->enabled())]]);
        $locale = $data['locale'];

        $request->session()->put(SetAuthLocale::SESSION_KEY, $locale);
        $user = Admin::guard()->user();
        if ($user instanceof Model) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }
        app()->setLocale($locale);

        $cookie = cookie(SetAuthLocale::cookieName(), $locale, 60 * 24 * 365, '/', null, null, true, false, 'lax');

        $response = $request->expectsJson()
            ? new JsonResponse(['locale' => $locale])
            : redirect()->back(fallback: route('login'));

        return $response->withCookie($cookie);
    }
}
