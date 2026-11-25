<?php

use Codeart\Core\Theme\Color;

it('computes WCAG contrast', function () {
    expect(Color::contrast('#000000', '#ffffff'))->toBe(21.0)
        ->and(Color::contrast('#ffffff', '#ffffff'))->toBe(1.0);
});

it('picks dark or light text by contrast', function () {
    expect(Color::readableOn('#c9f31d', '#22201f', '#ffffff'))->toBe('#22201f')
        ->and(Color::readableOn('#1a237e', '#22201f', '#ffffff'))->toBe('#ffffff');
});

it('expands short hex and rejects invalid values', function () {
    expect(Color::normalize('#FA0'))->toBe('#ffaa00')
        ->and(Color::isHex('red'))->toBeFalse()
        ->and(fn () => Color::toRgb('nope'))->toThrow(InvalidArgumentException::class);
});

it('moves a color until it reaches the required contrast', function () {
    $link = Color::ensureContrast('#c9f31d', '#ffffff', '#000000', 4.5);

    expect(Color::contrast($link, '#ffffff'))->toBeGreaterThanOrEqual(4.5);
});
