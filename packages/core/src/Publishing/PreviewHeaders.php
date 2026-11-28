<?php

namespace Codeart\Core\Publishing;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pushed on the `web` group by core: any response to a request that carries a preview parameter (valid or not) is
 * private, uncacheable and unindexable, and does not leak the link through the Referer header.
 */
class PreviewHeaders
{
    public function __construct(private readonly PreviewManager $preview) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($this->preview->requested($request) || $request->attributes->get('codeart.preview.active') === true) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Vary', 'Cookie', false);
        }

        return $response;
    }
}
