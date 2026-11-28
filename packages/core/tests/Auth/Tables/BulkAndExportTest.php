<?php

require_once __DIR__.'/../../Support/tables.php';

use Codeart\Core\Settings\Settings;
use Codeart\Core\Tables\BulkActionRunner;
use Codeart\Core\Tables\BulkLimitException;
use Codeart\Core\Tables\CsvExporter;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\RunBulkActionJob;
use Codeart\Core\Tables\TableDefinition;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    makeItemTables();
    app(TableRegistry::class)->register('items', ItemsDefinition::class);
    ItemsDefinition::$seen = [];
    ItemsDefinition::$handler = null;
});

function itemsUser(array $permissions = ['items.view', 'items.publish']): AdminTestUser
{
    $user = staff('editor');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'admin');
        $user->givePermissionTo($permission);
    }

    return $user->fresh();
}

function bulk($test, AdminTestUser $user, array $data)
{
    return $test->actingAs($user, 'admin')->from('/admin/items')->post(route('codeart.tables.bulk', ['table' => 'items']), $data);
}

function toast($response): array
{
    return $response->getSession()->get('toast');
}

/** @return list<string> row-loading selects on tbl_items from the query log, bindings filled in */
function itemSelects(): array
{
    $out = [];
    foreach (DB::getQueryLog() as $entry) {
        if (str_contains($entry['query'], 'from `tbl_items`') && ! str_contains($entry['query'], 'count(')) {
            $bindings = $entry['bindings'];
            $out[] = preg_replace_callback('/\?/', fn () => (string) array_shift($bindings), $entry['query']);
        }
    }

    return $out;
}

// ------------------------------------------------------------------------------------------------------------ bulk

it('runs the action on the ticked rows only and reports done, failed and skipped', function () {
    seedMany(6);
    ItemsDefinition::$handler = fn ($item) => match ($item->id) {
        1 => true,
        2 => false,
        3 => null,
        4 => throw new RuntimeException('boom'),
        default => true,
    };
    $this->withoutExceptionHandling([RuntimeException::class]);

    $response = bulk($this, itemsUser(), ['action' => 'publish', 'ids' => ['1', '2', '3', '4', '5']]);

    expect(ItemsDefinition::$seen)->toBe([1, 2, 3, 4, 5])
        ->and(toast($response))->toBe(['type' => 'warning', 'message' => 'Završeno: 2, neuspešno: 2, preskočeno: 1.'])
        ->and(ItemsDefinition::$seen)->not->toContain(6);
});

it('runs "select all matching" on the filter, not on a list of ids', function () {
    seedMany(30);
    $user = itemsUser();

    $response = bulk($this, $user, ['action' => 'publish', 'scope' => 'filter', 'filter' => ['status' => 'published'], 'q' => 'Item', 'ids' => ['1']]);

    $expected = DB::table('tbl_items')->where('status', 'published')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect(ItemsDefinition::$seen)->toBe($expected)->and(count($expected))->toBe(10)
        ->and(toast($response)['message'])->toBe('Završeno: 10, neuspešno: 0, preskočeno: 0.');
});

it('processes in chunks of the configured size and never loads all rows or ids at once', function () {
    seedMany(10);
    config(['codeart.tables.bulk.chunk' => 4]);
    $seenInChunk = [];
    ItemsDefinition::$handler = function ($item) use (&$seenInChunk) {
        $seenInChunk[] = $item->id;

        return true;
    };
    DB::enableQueryLog();

    bulk($this, itemsUser(), ['action' => 'publish', 'scope' => 'filter']);

    $selects = array_filter(itemSelects(), fn (string $q) => ! str_contains($q, 'activity_log'));
    $chunkQueries = array_values(array_filter($selects, fn (string $q) => str_contains($q, 'limit')));

    expect($seenInChunk)->toBe(range(1, 10))
        ->and($chunkQueries)->toHaveCount(3) // 4 + 4 + 2: boundaries at ids 4 and 8
        ->and($chunkQueries)->each->toContain('limit 4')
        ->and($chunkQueries[1])->toContain('`tbl_items`.`id` > 4')
        ->and($chunkQueries[2])->toContain('`tbl_items`.`id` > 8');
});

it('chunks an id selection too', function () {
    seedMany(10);
    config(['codeart.tables.bulk.chunk' => 3]);
    DB::enableQueryLog();

    bulk($this, itemsUser(), ['action' => 'publish', 'ids' => ['2', '3', '5', '7', '8', '9', '10']]);

    expect(ItemsDefinition::$seen)->toBe([2, 3, 5, 7, 8, 9, 10])
        ->and(array_filter(itemSelects(), fn (string $q) => str_contains($q, 'limit 3')))->toHaveCount(3);
});

it('runs inside the request up to the hard cap when no real queue is configured, and refuses above it', function () {
    seedMany(12);
    config(['codeart.tables.bulk.queue_limit' => 5, 'codeart.tables.bulk.hard_cap' => 12, 'queue.default' => 'sync']);
    Queue::fake();
    $user = itemsUser();

    $within = bulk($this, $user, ['action' => 'publish', 'scope' => 'filter']);
    expect(ItemsDefinition::$seen)->toHaveCount(12)->and(toast($within)['type'])->toBe('success');
    Queue::assertNothingPushed();

    ItemsDefinition::$seen = [];
    config(['codeart.tables.bulk.hard_cap' => 11]);
    $refused = bulk($this, $user, ['action' => 'publish', 'scope' => 'filter']);

    expect(ItemsDefinition::$seen)->toBe([])
        ->and(toast($refused))->toBe(['type' => 'danger', 'message' => 'Previše redova (12, najviše 11). Suzite filter i pokušajte ponovo.']);
    Queue::assertNothingPushed();
});

it('queues the action above the limit when a real queue driver is configured, and the job does the same chunked work', function () {
    seedMany(12);
    config(['codeart.tables.bulk.queue_limit' => 3, 'queue.default' => 'database', 'queue.connections.database.driver' => 'database']);
    Queue::fake();
    $user = itemsUser();

    $response = bulk($this, $user, ['action' => 'publish', 'scope' => 'filter', 'filter' => ['status' => 'draft']]);

    expect(ItemsDefinition::$seen)->toBe([])->and(toast($response)['type'])->toBe('info')->and(toast($response)['message'])->toContain('zakazana');
    Queue::assertPushed(RunBulkActionJob::class, function (RunBulkActionJob $job) use ($user) {
        expect($job->query)->toBe(['filter' => ['status' => 'draft']])->and($job->ids)->toBeNull()->and($job->userId)->toBe($user->getKey());
        $job->handle(app(TableRegistry::class), app(BulkActionRunner::class));

        return true;
    });
    expect(ItemsDefinition::$seen)->toHaveCount(4)->and(ItemsDefinition::$seen)->toBe(DB::table('tbl_items')->where('status', 'draft')->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all());
});

it('the queued job checks permission again', function () {
    seedMany(3);
    $user = itemsUser(['items.view']); // may see, may not publish

    $job = new RunBulkActionJob('items', 'publish', [], null, $user->getKey());

    expect(fn () => $job->handle(app(TableRegistry::class), app(BulkActionRunner::class)))->toThrow(AuthorizationException::class)
        ->and(ItemsDefinition::$seen)->toBe([]);
});

it('hides actions from users without permission and blocks them on the server', function () {
    seedMany(3);

    $noAction = bulk($this, itemsUser(['items.view']), ['action' => 'publish', 'ids' => ['1']]);
    $noView = bulk($this, itemsUser(['items.publish']), ['action' => 'publish', 'ids' => ['1']]);

    expect($noAction->status())->toBe(403)->and($noView->status())->toBe(403)->and(ItemsDefinition::$seen)->toBe([]);

    $table = (new ItemsDefinition)->table();
    $editor = itemsUser(['items.view']);
    expect($table->bulkAction('publish')->allowedFor($editor))->toBeFalse()->and($table->bulkAction('open')->allowedFor($editor))->toBeTrue()
        ->and($table->canView(null))->toBeFalse();
});

it('rejects unknown tables, unknown actions and oversized id lists safely', function () {
    seedMany(3);
    $user = itemsUser();

    expect($this->actingAs($user, 'admin')->post('/admin/tables/nope/bulk', ['action' => 'x'])->status())->toBe(404)
        ->and(toast(bulk($this, $user, ['action' => 'nope', 'ids' => ['1']]))['type'])->toBe('danger')
        ->and(toast(bulk($this, $user, ['action' => 'publish', 'ids' => array_map(strval(...), range(1, 101))]))['type'])->toBe('danger')
        ->and(ItemsDefinition::$seen)->toBe([]);

    // nothing selected = nothing done
    bulk($this, $user, ['action' => 'publish', 'ids' => []]);
    expect(ItemsDefinition::$seen)->toBe([]);
});

it('requires a signed-in staff user', function () {
    $this->post(route('codeart.tables.bulk', ['table' => 'items']), ['action' => 'publish'])->assertRedirect();
    $this->get(route('codeart.tables.export', ['table' => 'items']))->assertRedirect();
});

it('does not touch rows outside the ticked ids when the filter is ignored', function () {
    seedMany(5);
    bulk($this, itemsUser(), ['action' => 'publish', 'ids' => ['2'], 'filter' => ['status' => 'archived'], 'q' => 'zzz']);

    expect(ItemsDefinition::$seen)->toBe([2]);
});

// ---------------------------------------------------------------------------------------------------------- export

function csvRows(string $content): array
{
    $content = substr($content, 3); // BOM
    $rows = [];
    $h = fopen('php://memory', 'w+');
    fwrite($h, $content);
    rewind($h);
    while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }

    return $rows;
}

it('streams a CSV with a BOM, the table columns, the current filter and the current sort', function () {
    app()->setLocale('en');
    seedMany(30);
    $response = $this->actingAs(itemsUser(), 'admin')->get(route('codeart.tables.export', ['table' => 'items', 'filter' => ['status' => 'published'], 'sort' => 'views', 'dir' => 'desc']));
    $content = $response->streamedContent();
    $rows = csvRows($content);

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('items-'.now()->format('Y-m-d').'.csv')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($rows[0])->toBe(['ID', 'Title', 'Status', 'Views', 'Featured', 'Published', 'Category'])
        ->and($rows)->toHaveCount(11) // header + 10 published
        ->and(array_column($rows, 3))->toBe(['Views', '280', '250', '220', '190', '160', '130', '100', '70', '40', '10'])
        ->and($rows[1][2])->toBe('Published')->and($rows[1][4])->toBe('Da')->and($rows[1][1])->toBe('Item 028');
});

it('escapes cells that a spreadsheet would run as formulas', function () {
    seedItems([['title' => '=HYPERLINK("http://x")'], ['title' => '+1+1'], ['title' => '-2+3'], ['title' => '@SUM(A1)'], ['title' => "\tcmd"], ['title' => 'plain'], ['title' => 'says "hi", ok']]);
    $rows = csvRows($this->actingAs(itemsUser(), 'admin')->get(route('codeart.tables.export', ['table' => 'items']))->streamedContent());

    expect(array_column($rows, 1))->toBe(['Title', "'=HYPERLINK(\"http://x\")", "'+1+1", "'-2+3", "'@SUM(A1)", "'\tcmd", 'plain', 'says "hi", ok'])
        ->and(array_column($rows, 3)[1])->toBe('0'); // numbers stay numbers
    expect(CsvExporter::safe('=1'))->toBe("'=1")->and(CsvExporter::safe(-5))->toBe(-5)->and(CsvExporter::safe(null))->toBe('')->and(CsvExporter::safe(''))->toBe('');
});

it('writes in chunks and never loads all rows at once', function () {
    seedMany(10);
    config(['codeart.tables.export.chunk' => 3]);
    DB::enableQueryLog();

    $content = $this->actingAs(itemsUser(), 'admin')->get(route('codeart.tables.export', ['table' => 'items', 'sort' => 'title']))->streamedContent();

    $limited = array_values(array_filter(itemSelects(), fn (string $q) => str_contains($q, 'limit')));
    expect(csvRows($content))->toHaveCount(11)
        ->and($limited)->toHaveCount(4) // 3 + 3 + 3 + 1
        ->and($limited)->each->toContain('limit 3')
        ->and($limited[1])->toContain('offset 3');
});

it('uses keyset chunks when ordered by the key', function () {
    seedMany(7);
    config(['codeart.tables.export.chunk' => 3]);
    DB::enableQueryLog();

    $rows = csvRows($this->actingAs(itemsUser(), 'admin')->get(route('codeart.tables.export', ['table' => 'items', 'sort' => 'id', 'dir' => 'desc']))->streamedContent());

    $limited = array_values(array_filter(itemSelects(), fn (string $q) => str_contains($q, 'limit')));
    expect(array_column($rows, 0))->toBe(['ID', '7', '6', '5', '4', '3', '2', '1'])
        ->and($limited[1])->toContain('`tbl_items`.`id` < 5')->not->toContain('offset');
});

it('is permission protected, hidden when not enabled and 404 for an unknown table', function () {
    seedMany(2);

    expect($this->actingAs(itemsUser(['items.publish']), 'admin')->get(route('codeart.tables.export', ['table' => 'items']))->status())->toBe(403)
        ->and($this->actingAs(itemsUser(), 'admin')->get('/admin/tables/nope/export')->status())->toBe(404);

    app(TableRegistry::class)->register('plain', PlainDefinition::class);
    expect($this->actingAs(itemsUser(), 'admin')->get('/admin/tables/plain/export')->status())->toBe(404);
});

it('refuses an export above the row cap with a message instead of a truncated file', function () {
    seedMany(5);
    config(['codeart.tables.export.max_rows' => 4]);

    $response = $this->actingAs(itemsUser(), 'admin')->from('/admin/items')->get(route('codeart.tables.export', ['table' => 'items']));

    expect(toast($response)['type'])->toBe('danger')->and(toast($response)['message'])->toContain('Previše redova (5, najviše 4)');
    expect(fn () => app(CsvExporter::class)->response('items', itemsTable(), itemsTable()->stateFrom([]), null))->toThrow(BulkLimitException::class);
});

it('exports only the first N rows when asked, with constant memory per chunk', function () {
    seedMany(40);
    config(['codeart.tables.export.chunk' => 7]);
    $out = fopen('php://memory', 'w+');

    $written = app(CsvExporter::class)->write($out, itemsTable(), itemsTable()->stateFrom([]), 15);

    rewind($out);
    expect($written)->toBe(15)->and(csvRows((string) stream_get_contents($out)))->toHaveCount(16);
});

/** A table without export. */
class PlainDefinition implements TableDefinition
{
    public function table(): DataTable
    {
        return itemsTable();
    }
}
