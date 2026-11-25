<?php

namespace Codeart\Core\Theme;

use InvalidArgumentException;

/**
 * Small color math helpers (hex only, WCAG 2.x contrast).
 */
final class Color
{
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
    }

    /**
     * @return array{int, int, int}
     */
    public static function toRgb(string $hex): array
    {
        if (! self::isHex($hex)) {
            throw new InvalidArgumentException("Invalid hex color [{$hex}].");
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function fromRgb(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    public static function normalize(string $hex): string
    {
        return self::fromRgb(...self::toRgb($hex));
    }

    /** WCAG relative luminance, 0 (black) to 1 (white). */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::toRgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** WCAG contrast ratio, 1 to 21. */
    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** Mix $hex towards $target by $amount (0..1). */
    public static function mix(string $hex, string $target, float $amount): string
    {
        $from = self::toRgb($hex);
        $to = self::toRgb($target);

        return self::fromRgb(
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        );
    }

    /** Pick whichever of $dark / $light has the higher contrast on $background. */
    public static function readableOn(string $background, string $dark, string $light): string
    {
        return self::contrast($background, $dark) >= self::contrast($background, $light)
            ? self::normalize($dark)
            : self::normalize($light);
    }

    /**
     * Move $hex towards $towards in small steps until it reaches $minContrast on $background.
     * Returns the best effort when the target cannot be reached.
     */
    public static function ensureContrast(string $hex, string $background, string $towards, float $minContrast): string
    {
        $color = self::normalize($hex);

        for ($step = 0; $step <= 20; $step++) {
            $candidate = self::mix($color, $towards, $step / 20);
            if (self::contrast($candidate, $background) >= $minContrast) {
                return $candidate;
            }
        }

        return self::normalize($towards);
    }
}
