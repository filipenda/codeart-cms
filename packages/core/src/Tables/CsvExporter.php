<?php

namespace Codeart\Core\Tables;

use Codeart\Core\ActivityLog\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a table as CSV for Excel: UTF-8 with a BOM, `;`-free standard commas, the current search, filter and sort,
 * the columns of the table definition, written chunk by chunk (never all rows in memory), and cells that start with
 * `= + - @` (or a tab / CR) prefixed with an apostrophe so a spreadsheet cannot run them as a formula.
 */
final class CsvExporter
{
    public const string BOM = "\xEF\xBB\xBF";

    /** @throws BulkLimitException when more rows match than `export.max_rows` */
    public function response(string $tableKey, DataTable $table, TableState $state, ?Authenticatable $user): StreamedResponse
    {
        $max = (int) config('codeart.tables.export.max_rows', 500000);
        $matched = $table->filteredQuery($state)->toBase()->count();
        if ($matched > $max) {
            throw new BulkLimitException($matched, $max);
        }
        app(ActivityLog::class)->record('table.export', "Export of {$table->id}", null, $user instanceof Model ? $user : null, ['table' => $table->id, 'rows' => $matched], 'tables');

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $table->exportName() ?? $tableKey).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($table, $state, $max): void {
            @set_time_limit(0);
            $out = fopen('php://output', 'w');
            $this->write($out, $table, $state, $max);
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Accel-Buffering' => 'no', // nginx: do not buffer the stream
        ]);
    }

    /**
     * Write BOM, header and rows to `$out`; returns the number of data rows.
     *
     * @param  resource  $out
     */
    public function write($out, DataTable $table, TableState $state, ?int $limit = null): int
    {
        $columns = array_values(array_filter($table->allColumns(), fn (Column $c): bool => $c->exportable && $c->type !== 'actions'));
        fwrite($out, self::BOM);
        $this->line($out, array_map(fn (Column $c): string => $c->label, $columns));

        $count = 0;
        $table->eachOrdered($state, (int) config('codeart.tables.export.chunk', 1000), function (Collection $rows) use ($out, $columns, &$count): void {
            foreach ($rows as $row) {
                $this->line($out, array_map(fn (Column $c) => $c->exportValue($row), $columns));
                $count++;
            }
            if (function_exists('flush') && PHP_SAPI !== 'cli') {
                flush();
            }
        }, $limit);

        return $count;
    }

    /** Formula-injection guard: text that a spreadsheet would execute gets a leading apostrophe. Numbers are left alone. */
    public static function safe(string|int|float|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value) && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  resource  $out
     * @param  list<string|int|float|null>  $cells
     */
    private function line($out, array $cells): void
    {
        fputcsv($out, array_map(self::safe(...), $cells), ',', '"', '', "\r\n");
    }
}
