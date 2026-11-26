<?php

use Codeart\Core\Admin\Dashboard\WidgetRows;

it('packs and fills rows', function (array $in, array $rows) {
    expect(WidgetRows::pack($in))->toBe($rows);
})->with([
    '6+3 becomes 8+4' => [[6, 3], [[8, 4]]],
    '3+3+3 becomes 4+4+4' => [[3, 3, 3], [[4, 4, 4]]],
    '3x4 stays' => [[3, 3, 3, 3], [[3, 3, 3, 3]]],
    '6+6 stays' => [[6, 6], [[6, 6]]],
    '12 stays' => [[12], [[12]]],
    '3 alone keeps its size' => [[3], [[3]]],
    '6+3+3 stays' => [[6, 3, 3], [[6, 3, 3]]],
    '6+3 then 12 then 3' => [[6, 3, 12, 3], [[8, 4], [12], [3]]],
    'order preserved over rows' => [[6, 6, 3, 3], [[6, 6], [6, 6]]],
    'rounding goes to widest' => [[6, 3, 3, 6, 3, 3, 3, 3, 3], [[6, 3, 3], [6, 3, 3], [4, 4, 4]]],
    'empty' => [[], []],
]);

it('returns a flat span list in widget order', function () {
    expect(WidgetRows::spans([6, 3, 12]))->toBe([8, 4, 12])->and(WidgetRows::spans([]))->toBe([]);
});
