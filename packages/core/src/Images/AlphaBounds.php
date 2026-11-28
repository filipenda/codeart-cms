<?php

namespace Codeart\Core\Images;

/**
 * Finds the tight box of the visible pixels of an image with an alpha channel (GD only; GD is a Laravel requirement).
 * A pixel counts as visible when its opacity is above the tolerance, so faint shadows and glows do not stop the trim.
 * Rows are scanned from the top and from the bottom, then columns only inside those rows: a typical logo is
 * found after reading a small part of the pixels.
 */
final class AlphaBounds
{
    /**
     * @param  int  $tolerance  0-255, opacity at or below it is treated as empty
     * @return array{x: int, y: int, width: int, height: int}|null null when no pixel is visible
     */
    public static function of(string $bytes, int $tolerance): ?array
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);
        // GD alpha: 0 opaque .. 127 transparent. Visible when opacity (0-255 scale) > tolerance.
        $visible = static function (int $x, int $y) use ($image, $tolerance): bool {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;

            return (127 - $alpha) * 255 / 127 > $tolerance;
        };
        $rowHasVisible = static function (int $y, int $from, int $to) use ($visible): bool {
            for ($x = $from; $x <= $to; $x++) {
                if ($visible($x, $y)) {
                    return true;
                }
            }

            return false;
        };

        $top = null;
        for ($y = 0; $y < $height; $y++) {
            if ($rowHasVisible($y, 0, $width - 1)) {
                $top = $y;
                break;
            }
        }
        if ($top === null) {
            return null;
        }
        $bottom = $top;
        for ($y = $height - 1; $y > $top; $y--) {
            if ($rowHasVisible($y, 0, $width - 1)) {
                $bottom = $y;
                break;
            }
        }

        $left = $width - 1;
        $right = 0;
        for ($y = $top; $y <= $bottom; $y++) {
            // Only look further out than what is already known.
            for ($x = 0; $x < $left; $x++) {
                if ($visible($x, $y)) {
                    $left = $x;
                    break;
                }
            }
            for ($x = $width - 1; $x > $right; $x--) {
                if ($visible($x, $y)) {
                    $right = $x;
                    break;
                }
            }
        }
        $right = max($right, $left);

        return ['x' => $left, 'y' => $top, 'width' => $right - $left + 1, 'height' => $bottom - $top + 1];
    }
}
