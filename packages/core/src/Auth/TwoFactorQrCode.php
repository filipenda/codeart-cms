<?php

namespace Codeart\Core\Auth;

/**
 * Re-wraps the QR SVG made by Fortify (dark modules, white background, NO margin) so that it always has
 * the 4-module quiet zone scanners need and uses the fixed QR tokens (`--color-qr-fg` / `--color-qr-bg`,
 * identical in every theme). Only the module path is kept from Fortify's output.
 */
final class TwoFactorQrCode
{
    public const int QUIET_ZONE = 4;

    public static function withQuietZone(string $svg): string
    {
        $size = preg_match('/<svg[^>]*\swidth="(\d+(?:\.\d+)?)"/', $svg, $w) === 1 ? (float) $w[1] : 0.0;
        $scale = preg_match('/<g transform="scale\((\d+(?:\.\d+)?)\)"/', $svg, $s) === 1 ? (float) $s[1] : 0.0;
        $paths = preg_match_all('/<path[^>]*\sd="([^"]+)"/', $svg, $p) ? $p[1] : [];

        if ($size <= 0 || $scale <= 0 || $paths === []) {
            throw new \RuntimeException('Unexpected two-factor QR code SVG.');
        }

        $modules = (int) round($size / $scale);
        $total = $modules + 2 * self::QUIET_ZONE;
        $q = self::QUIET_ZONE;
        $d = implode('', $paths);

        return '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 '.$total.' '.$total.'" shape-rendering="crispEdges" aria-hidden="true" focusable="false">'
            .'<rect x="0" y="0" width="'.$total.'" height="'.$total.'" style="fill:var(--color-qr-bg)"/>'
            .'<g transform="translate('.$q.','.$q.')"><path fill-rule="evenodd" style="fill:var(--color-qr-fg)" d="'.$d.'"/></g></svg>';
    }
}
