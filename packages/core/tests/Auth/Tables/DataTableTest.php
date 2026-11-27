<?php

require_once __DIR__.'/../../Support/tables.php';

use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\TableState;
use Codeart\Core\Tests\Support\TableItem;

beforeEach(function () {
    makeItemTables();
});

// ----------------------------------------------------------------------------------------------- paging and clamping

it('loads exactly one page of the default size with an exact total', function () {
    seedMany(60);
    $result = itemsTable()->resolve(tableRequest());

    expect($result->rows)->toHaveCount(25)
        ->and($result->total)->toBe(60)
        ->and($result->lastPage())->toBe(3)
        ->and([$result->from(), $result->to()])->toBe([1, 25])
        ->and(titles($result)[0])->toBe('Item 001');
});

it('clamps per_page to 1..100 and falls back to the default for nonsense', function (mixed $raw, int $expected) {
    $state = itemsTable()->stateFrom(['per_page' => $raw]);

    expect($state->perPage)->toBe($expected);
})->with([
    'too big' => ['5000', 100],
    'exactly 100' => ['100', 100],
    'zero' => ['0', 25],
    'negative' => ['-5', 25],
    'text' => ['abc', 25],
    'array' => [['x'], 25],
    'float' => ['10.5', 25],
    'huge' => ['99999999999999999999999', 25],
    'valid' => ['50', 50],
]);

it('never loads more than 100 rows even when asked for more', function () {
    seedMany(250);
    $result = itemsTable()->resolve(tableRequest(['per_page' => '1000']));

    expect($result->rows)->toHaveCount(100);
});

it('treats an invalid page as the first and clamps a huge one', function (mixed $raw, int $expected) {
    expect(itemsTable()->stateFrom(['page' => $raw])->page)->toBe($expected);
})->with([['0', 1], ['-3', 1], ['x', 1], [['1'], 1], ['3', 3], ['99999999999999999999999', 1], ['999999999999', TableState::MAX_PAGE]]);

it('redirects a page past the end to the last page, keeping the other state', function () {
    seedMany(60);
    $result = itemsTable()->resolve(tableRequest(['page' => '99', 'q' => 'Item', 'per_page' => '10']));

    expect($result->redirectTo)->toBe('http://localhost/admin/items?q=Item&per_page=10&page=6')
        ->and($result->rows)->toHaveCount(0);
});

it('sends a page past an empty table to the first page, and never redirects the last page', function () {
    expect(itemsTable()->resolve(tableRequest(['page' => '7']))->redirectTo)->toBe('http://localhost/admin/items')->and(itemsTable()->resolve(tableRequest(['page' => '1']))->rows)->toHaveCount(0);

    seedMany(30);
    $last = itemsTable()->resolve(tableRequest(['page' => '2']));

    expect($last->redirectTo)->toBeNull()->and($last->rows)->toHaveCount(5)->and([$last->from(), $last->to()])->toBe([26, 30]);
});

// -------------------------------------------------------------------------------------------------------------- sort

it('sorts by every declared column in both directions, with the key as a stable tie-breaker', function (string $column) {
    seedMany(40);
    $table = itemsTable();

    foreach (['asc', 'desc'] as $dir) {
        $sql = $table->orderedQuery($table->stateFrom(['sort' => $column, 'dir' => $dir]))->toRawSql();
        $rows = $table->resolve(tableRequest(['sort' => $column, 'dir' => $dir, 'per_page' => '100']))->rows;

        expect($rows)->toHaveCount(40)->and($sql)->toContain('order by')->toMatch('/`tbl_items`\.`id` '.$dir.'$/');
    }
})->with(['id', 'title', 'status', 'views', 'featured', 'published_at', 'category']);

it('orders by the sorted column and breaks ties by id', function () {
    seedItems([
        ['title' => 'B', 'status' => 'draft', 'views' => 5],
        ['title' => 'A', 'status' => 'draft', 'views' => 5],
        ['title' => 'C', 'status' => 'draft', 'views' => 1],
        ['title' => 'D', 'status' => 'draft', 'views' => 5],
    ]);
    $table = itemsTable();

    expect(titles($table->resolve(tableRequest(['sort' => 'views', 'dir' => 'asc']))))->toBe(['C', 'B', 'A', 'D'])
        ->and(titles($table->resolve(tableRequest(['sort' => 'views', 'dir' => 'desc']))))->toBe(['D', 'A', 'B', 'C']);
});

it('keeps the order stable across pages (no row twice, none missing)', function () {
    seedItems(array_map(fn ($i) => ['title' => 'T'.$i, 'status' => 'draft', 'views' => 7], range(1, 30)));
    $seen = [];
    foreach ([1, 2, 3] as $page) {
        $seen = [...$seen, ...itemsTable()->resolve(tableRequest(['sort' => 'views', 'per_page' => '10', 'page' => (string) $page]))->rows->pluck('id')->all()];
    }

    expect($seen)->toHaveCount(30)->and(array_unique($seen))->toHaveCount(30);
});

it('ignores a sort key that is not a declared sortable column, and never puts it in SQL', function (string $evil) {
    seedMany(5);
    $table = itemsTable();
    $state = $table->stateFrom(['sort' => $evil, 'dir' => 'asc; drop table tbl_items']);
    $sql = $table->orderedQuery($state)->toRawSql();

    expect($state->sort)->toBe('id')->and($state->dir)->toBe('asc')->and($sql)->not->toContain('drop')->not->toContain(str_replace('`', '', $evil) === 'password' ? 'password' : 'zzzz')
        ->and(fn () => $table->resolve(tableRequest(['sort' => $evil])))->not->toThrow(Throwable::class);
})->with(['password', 'title; drop table tbl_items', '1 desc, (select 1)', 'id`']);

// ------------------------------------------------------------------------------------------------------------ search

it('searches "contains" over the searchable columns', function () {
    seedItems([['title' => 'Alpha news'], ['title' => 'Beta blog'], ['title' => 'Gamma']]);

    expect(titles(itemsTable()->resolve(tableRequest(['q' => 'eta']))))->toBe(['Beta blog'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => 'ALPHA']))))->toBe(['Alpha news']);
});

it('treats % and _ as literal characters', function () {
    seedItems([['title' => '100% real'], ['title' => '100 percent'], ['title' => 'snake_case'], ['title' => 'snakeXcase'], ['title' => 'back\\slash'], ['title' => "it's!"]]);

    expect(titles(itemsTable()->resolve(tableRequest(['q' => '100%']))))->toBe(['100% real'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => 'e_c']))))->toBe(['snake_case'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => '%']))))->toBe(['100% real'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => '_']))))->toBe(['snake_case'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => '\\']))))->toBe(['back\\slash'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => 's!']))))->toBe(["it's!"])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => "'; --"]))))->toBe([]);
});

it('ANDs several words, each matching any searchable column', function () {
    seedItems([
        ['title' => 'Red apple', 'status' => 'published'],
        ['title' => 'Red car', 'status' => 'draft'],
        ['title' => 'Green apple', 'status' => 'published'],
    ]);

    expect(titles(itemsTable()->resolve(tableRequest(['q' => 'red apple']))))->toBe(['Red apple'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => 'apple published']))))->toBe(['Red apple', 'Green apple'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => '  red   car  ']))))->toBe(['Red car'])
        ->and(titles(itemsTable()->resolve(tableRequest(['q' => 'red green']))))->toBe([]);
});

it('cuts an absurdly long search and ignores a non-string one', function () {
    expect(itemsTable()->stateFrom(['q' => str_repeat('a', 5000)])->q)->toHaveLength(TableState::MAX_SEARCH)
        ->and(itemsTable()->stateFrom(['q' => ['x']])->q)->toBe('');
});

// ---------------------------------------------------------------------------------------------------------- filters

it('applies the declared filters and ignores invalid values', function () {
    seedMany(30);
    $table = itemsTable();

    $published = $table->resolve(tableRequest(['filter' => ['status' => 'published'], 'per_page' => '100']));
    $none = $table->resolve(tableRequest(['filter' => ['status' => 'nonsense']]));
    $featured = $table->resolve(tableRequest(['filter' => ['featured' => '1'], 'per_page' => '100']));
    $notFeatured = $table->resolve(tableRequest(['filter' => ['featured' => '0'], 'per_page' => '100']));

    expect($published->total)->toBe(10)->and($published->rows->pluck('status')->unique()->all())->toBe(['published'])
        ->and($none->total)->toBe(30)
        ->and($featured->total)->toBe(15)->and($notFeatured->total)->toBe(15)
        ->and($table->resolve(tableRequest(['filter' => ['featured' => 'maybe']]))->total)->toBe(30)
        ->and($table->resolve(tableRequest(['filter' => 'nonsense']))->total)->toBe(30)
        ->and($table->resolve(tableRequest(['filter' => ['unknown' => 'x']]))->total)->toBe(30);
});

it('filters a date range inclusively and ignores bad dates', function () {
    seedItems([
        ['title' => 'a', 'published_at' => '2026-03-01 00:00:00'],
        ['title' => 'b', 'published_at' => '2026-03-05 23:59:59'],
        ['title' => 'c', 'published_at' => '2026-03-06 00:00:00'],
        ['title' => 'd', 'published_at' => null],
    ]);
    $table = itemsTable();
    $range = fn (array $r) => titles($table->resolve(tableRequest(['filter' => ['published_at' => $r]])));

    expect($range(['from' => '2026-03-01', 'to' => '2026-03-05']))->toBe(['a', 'b'])
        ->and($range(['from' => '2026-03-02']))->toBe(['b', 'c'])
        ->and($range(['to' => '2026-03-01']))->toBe(['a'])
        ->and($range(['from' => '2026-02-30', 'to' => 'x']))->toBe(['a', 'b', 'c', 'd'])
        ->and($table->stateFrom(['filter' => ['published_at' => '2026-03-01']])->filters)->toBe([]);
});

it('filters every column, not only the declared filters (text contains, number range, select)', function () {
    seedMany(20);
    $table = itemsTable();

    expect($table->resolve(tableRequest(['filter' => ['title' => 'Item 01']]))->total)->toBe(10)
        ->and($table->resolve(tableRequest(['filter' => ['views' => ['from' => '50', 'to' => '80']]]))->total)->toBe(4)
        ->and($table->resolve(tableRequest(['filter' => ['views' => '50']]))->total)->toBe(1)
        ->and($table->resolve(tableRequest(['filter' => ['views' => ['from' => 'abc']]]))->total)->toBe(20)
        ->and($table->resolve(tableRequest(['filter' => ['category' => '1']]))->total)->toBe(10);
});

it('combines search, filters and sorting, and the total follows the filter', function () {
    seedMany(30);
    $result = itemsTable()->resolve(tableRequest(['q' => 'Item 0', 'filter' => ['status' => 'draft'], 'sort' => 'views', 'dir' => 'desc', 'per_page' => '5']));

    expect($result->total)->toBe(10)->and($result->rows)->toHaveCount(5)
        ->and($result->rows->pluck('views')->all())->toBe($result->rows->pluck('views')->sortDesc()->values()->all())
        ->and($result->rows->pluck('status')->unique()->all())->toBe(['draft']);
});

// ------------------------------------------------------------------------------------------------------- state / URLs

it('round-trips the state through the query string and drops defaults', function () {
    $table = itemsTable();
    $state = $table->stateFrom(['q' => 'x', 'sort' => 'views', 'dir' => 'desc', 'filter' => ['status' => 'draft', 'featured' => '1'], 'per_page' => '50', 'page' => '2']);

    expect($state->toQuery($table))->toBe(['q' => 'x', 'sort' => 'views', 'dir' => 'desc', 'filter' => ['status' => 'draft', 'featured' => '1'], 'per_page' => 50, 'page' => 2])
        ->and($table->stateFrom([])->toQuery($table))->toBe([])
        ->and($table->stateFrom($state->toQuery($table)))->toEqual($state);
});

it('builds urls for a sort link and a page link', function () {
    seedMany(5);
    $result = itemsTable()->resolve(tableRequest(['q' => 'it']));

    expect($result->url(['sort' => 'title', 'dir' => 'desc']))->toBe('http://localhost/admin/items?q=it&sort=title&dir=desc')
        ->and($result->url(['page' => 1, 'q' => '']))->toBe('http://localhost/admin/items');
});

it('uses a stable default sort when none is declared', function () {
    $table = DataTable::make('x', TableItem::query())->columns(Column::text('title', 'T')->sortable(false), Column::text('status', 'S'));

    expect($table->defaultSortOrder())->toBe(['status', 'asc']);
});
