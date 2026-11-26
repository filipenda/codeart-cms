<?php

namespace Codeart\Core\Admin\Dashboard;

/**
 * Packs widgets (declared column widths) into rows of 12 in order. A row with two or more widgets that
 * falls short of 12 is scaled up proportionally to exactly 12 (the widest gets the rounding rest);
 * a row with one widget keeps its declared width.
 */
final class WidgetRows
{
    public const int COLUMNS = 12;

    /**
     * @param  list<int>  $widths
     * @return list<list<int>> rows of final widths, order preserved
     */
    public static function pack(array $widths): array
    {
        $rows = [];
        $row = [];
        foreach ($widths as $w) {
            $w = max(1, min(self::COLUMNS, $w));
            if ($row !== [] && array_sum($row) + $w > self::COLUMNS) {
                $rows[] = $row;
                $row = [];
            }
            $row[] = $w;
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return array_map(self::fill(...), $rows);
    }

    /**
     * @param  list<int>  $widths
     * @return list<int> final width per widget, same order
     */
    public static function spans(array $widths): array
    {
        return array_merge(...self::pack($widths) ?: [[]]);
    }

    /**
     * @param  list<int>  $row
     * @return list<int>
     */
    private static function fill(array $row): array
    {
        $sum = array_sum($row);
        if (count($row) < 2 || $sum >= self::COLUMNS) {
            return $row;
        }
        $scaled = array_map(fn (int $w): int => intdiv($w * self::COLUMNS, $sum), $row);
        $rest = self::COLUMNS - array_sum($scaled);
        $widest = array_search(max($row), $row, true);
        $scaled[$widest] += $rest;

        return $scaled;
    }
}
