<?php

namespace Codeart\Core\Profile;

/**
 * A small built-in user-agent reader for the session list (no library, no network): browser, OS and a device kind.
 * Order matters: Edge, Opera and Chrome-on-iOS also say "Chrome" or "Safari" in their string, so the specific names go first.
 * Anything it does not recognise is "unknown" (the screen then says "Nepoznat uređaj").
 */
final class UserAgent
{
    /** @var list<array{0: string, 1: string}> regex => name, most specific first */
    private const array BROWSERS = [
        ['/\b(?:Edg|EdgA|EdgiOS|Edge)\//', 'Edge'],
        ['/\b(?:OPR|OPT)\/|\bOpera\b/', 'Opera'],
        ['/\b(?:Firefox|FxiOS)\//', 'Firefox'],
        ['/\b(?:Chrome|CriOS|Chromium)\//', 'Chrome'],
        ['/\bSafari\//', 'Safari'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const array SYSTEMS = [
        ['/Windows/', 'Windows'],
        ['/Android/', 'Android'],
        ['/iPhone|iPad|iPod/', 'iOS'],
        ['/Macintosh|Mac OS X/', 'macOS'],
        ['/CrOS/', 'ChromeOS'],
        ['/Linux|X11/', 'Linux'],
    ];

    /** @return array{browser: ?string, os: ?string, device: 'desktop'|'laptop'|'phone'|'tablet', known: bool} */
    public static function parse(?string $agent): array
    {
        $agent = (string) $agent;
        $browser = self::first(self::BROWSERS, $agent);
        $os = self::first(self::SYSTEMS, $agent);

        $tablet = (bool) preg_match('/iPad|Tablet|\bAndroid\b(?!.*Mobile)/i', $agent);
        $phone = ! $tablet && (bool) preg_match('/iPhone|iPod|Mobile|Windows Phone/i', $agent);
        $device = $tablet ? 'tablet' : ($phone ? 'phone' : ($os === 'macOS' ? 'laptop' : 'desktop'));

        return ['browser' => $browser, 'os' => $os, 'device' => $device, 'known' => $browser !== null || $os !== null];
    }

    /** @param  list<array{0: string, 1: string}>  $table */
    private static function first(array $table, string $agent): ?string
    {
        foreach ($table as [$pattern, $name]) {
            if (preg_match($pattern, $agent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
