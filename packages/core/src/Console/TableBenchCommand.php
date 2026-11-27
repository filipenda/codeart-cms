<?php

namespace Codeart\Core\Console;

use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\CsvExporter;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\Filter;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Developer benchmark of the data table code path on a big temporary table (MariaDB only: it fills the table with the
 * sequence engine). Not part of CI or the default test run. Creates `codeart_bench_items`, runs the real DataTable and
 * CsvExporter against it, prints timings and always drops the table again.
 */
class TableBenchCommand extends Command
{
    protected $signature = 'codeart:bench:table
        {--rows=1000000 : Rows to generate}
        {--runs=3 : Repetitions per operation (best and median are shown)}
        {--export-rows=100000 : Rows of the CSV export}
        {--force : Allow it in the production environment}';

    protected $description = 'Benchmark the data table on a temporary table (default 1,000,000 rows) and drop it afterwards';

    public const string BENCH_TABLE = 'codeart_bench_items';

    private const string TABLE = self::BENCH_TABLE;

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to run in production (use --force).');

            return self::FAILURE;
        }
        if (! in_array(DB::connection()->getDriverName(), ['mariadb'], true)) {
            $this->components->error('This benchmark needs MariaDB (it fills the table from the sequence engine).');

            return self::FAILURE;
        }

        $rows = max(1000, (int) $this->option('rows'));
        $runs = max(1, (int) $this->option('runs'));
        $results = [];

        try {
            $this->components->info('Creating and filling '.number_format($rows).' rows ...');
            $this->fill($rows);
            $table = $this->definition();

            $deepPage = min(20000, intdiv($rows, 50)); // page 20,000 of 25 = offset 499,975 on the full 1,000,000 rows
            $operations = [
                'first page (default order, with exact count)' => fn () => $table->resolve($this->request([])),
                "deep page {$deepPage} (25 per page)" => fn () => $table->resolve($this->request(['page' => $deepPage])),
                'exact count (no filter)' => fn () => $table->filteredQuery($table->stateFrom([]))->toBase()->count(),
                'search "contains" on a text column' => fn () => $table->resolve($this->request(['q' => 'Article 54321'])),
                'sort by an unindexed column (title)' => fn () => $table->resolve($this->request(['sort' => 'title', 'dir' => 'asc'])),
                'sort by an unindexed number (created_by)' => fn () => $table->resolve($this->request(['sort' => 'created_by', 'dir' => 'desc'])),
                'sort by an indexed column (created_at)' => fn () => $table->resolve($this->request(['sort' => 'created_at', 'dir' => 'desc'])),
                'one filter (status = published)' => fn () => $table->resolve($this->request(['filter' => ['status' => 'published']])),
                'one filter (category 7, indexed)' => fn () => $table->resolve($this->request(['filter' => ['category_id' => '7']])),
                'filter + search + sort combined' => fn () => $table->resolve($this->request(['filter' => ['status' => 'published'], 'q' => 'ipsum 1234', 'sort' => 'created_at', 'dir' => 'desc'])),
            ];
            foreach ($operations as $name => $operation) {
                $results[] = $this->measure($name, $operation, $runs);
            }

            $results[] = $this->export($table, min($rows, max(1, (int) $this->option('export-rows'))));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            Schema::dropIfExists(self::TABLE);
            $this->components->info('Dropped '.self::TABLE.'.');
        }

        $this->table(['Operation', 'Best (ms)', 'Median (ms)', 'Note'], array_map(fn (array $r) => [$r['name'], number_format($r['best'], 1), number_format($r['median'], 1), $r['note'].($r['median'] > 1000 ? ' SLOW (> 1 s)' : '')], $results));
        $slow = array_filter($results, fn (array $r) => $r['median'] > 1000);
        $this->line($slow === [] ? 'Nothing exceeds 1 second.' : 'Over 1 second: '.implode('; ', array_column($slow, 'name')));

        return self::SUCCESS;
    }

    /** @return array{name: string, best: float, median: float, note: string} */
    private function measure(string $name, callable $operation, int $runs): array
    {
        $times = [];
        $note = '';
        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $result = $operation();
            $times[] = (hrtime(true) - $start) / 1e6;
            if (is_object($result) && property_exists($result, 'total')) {
                $note = number_format($result->total).' matching, '.$result->rows->count().' rows shown';
            } elseif (is_int($result)) {
                $note = number_format($result).' rows';
            }
        }
        sort($times);

        return ['name' => $name, 'best' => $times[0], 'median' => $times[intdiv(count($times), 2)], 'note' => $note];
    }

    /** @return array{name: string, best: float, median: float, note: string} */
    private function export(DataTable $table, int $limit): array
    {
        $out = fopen('php://temp/maxmemory:1024', 'w+'); // spills to disk at 1 KB, so memory use is the exporter's own
        memory_reset_peak_usage();
        $before = memory_get_peak_usage(true);
        $start = hrtime(true);
        $written = app(CsvExporter::class)->write($out, $table, $table->stateFrom([]), $limit);
        $ms = (hrtime(true) - $start) / 1e6;
        $peak = (memory_get_peak_usage(true) - $before) / 1048576;
        $size = ftell($out) / 1048576;
        fclose($out);

        return ['name' => 'CSV export of the first '.number_format($limit).' rows', 'best' => $ms, 'median' => $ms, 'note' => sprintf('%s rows written, %.1f MB file, peak memory +%.1f MB', number_format($written), $size, $peak)];
    }

    private function fill(int $rows): void
    {
        Schema::dropIfExists(self::TABLE);
        $start = microtime(true);
        DB::statement('CREATE TABLE '.self::TABLE.' (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            created_by INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        DB::statement('INSERT INTO '.self::TABLE." (title, status, category_id, created_at, created_by)
            SELECT CONCAT('Article ', seq, ' ', ELT(1 + seq MOD 8, 'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit')),
                   ELT(1 + seq MOD 3, 'draft', 'published', 'archived'),
                   1 + seq MOD 50,
                   TIMESTAMP('2026-01-01 00:00:00') + INTERVAL (seq MOD 365) DAY + INTERVAL ((seq * 7919) MOD 86400) SECOND,
                   1 + (seq * 31) MOD 200
            FROM seq_1_to_{$rows}");
        // Secondary indexes after the bulk load (much faster). title and created_by stay unindexed on purpose.
        DB::statement('ALTER TABLE '.self::TABLE.' ADD INDEX bench_status (status), ADD INDEX bench_category (category_id), ADD INDEX bench_created_at (created_at)');
        DB::statement('ANALYZE TABLE '.self::TABLE);
        $this->line(sprintf('  filled in %.1f s', microtime(true) - $start));
    }

    private function definition(): DataTable
    {
        return DataTable::make('bench', BenchItem::query())
            ->columns(
                Column::number('id', 'ID'),
                Column::titleSubtitle('title', 'Title'),
                Column::status('status', 'Status', ['draft' => ['Draft', 'neutral'], 'published' => ['Published', 'success'], 'archived' => ['Archived', 'warning']]),
                Column::number('category_id', 'Category'),
                Column::date('created_at', 'Created'),
                Column::number('created_by', 'Author'),
            )
            ->filters(Filter::status('status', 'Status', ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']))
            ->defaultSort('id', 'asc')
            ->export('bench');
    }

    /** @param array<string, mixed> $query */
    private function request(array $query): Request
    {
        return Request::create('/bench', 'GET', $query);
    }
}
