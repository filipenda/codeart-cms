<?php

require_once __DIR__.'/../../Support/tables.php';
require_once __DIR__.'/../../Support/dom.php';

use Codeart\Core\Settings\Settings;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Tests\Support\AdminTestUser;
use Codeart\Core\Tests\Support\TableItem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    makeItemTables();
    app(TableRegistry::class)->register('items', ItemsDefinition::class);
    view()->share('errors', new ViewErrorBag);
    app()->setLocale('sr');
});

function tableUser(array $permissions): AdminTestUser
{
    $user = staff('editor');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'admin');
        $user->givePermissionTo($permission);
    }

    return $user->fresh();
}

/** Render the registered items table for a user holding `$permissions`. */
function renderItems(array $query = [], array $permissions = ['items.view', 'items.publish', 'items.edit', 'items.delete'], ?DataTable $table = null): DOMXPath
{
    $user = tableUser($permissions);
    $table ??= (new ItemsDefinition)->table();
    $result = $table->resolve(tableRequest($query), $user);

    return dom(Blade::render('<x-codeart::table :result="$result" label="Items" />', ['result' => $result]));
}

// ------------------------------------------------------------------------------------------------------------ toolbar

it('renders the toolbar as a GET form with the search, the declared filters and the current values', function () {
    seedMany(5);
    $xp = renderItems(['q' => 'Item', 'filter' => ['status' => 'draft', 'featured' => '1'], 'sort' => 'views', 'dir' => 'desc']);

    expect(attr($xp, '//form[@data-toolbar]', 'method'))->toBe('GET')
        ->and(attr($xp, '//form[@data-toolbar]', 'role'))->toBe('search')
        ->and(attr($xp, '//input[@name="q"]', 'value'))->toBe('Item')
        ->and(attr($xp, '//input[@name="q"]', 'aria-label'))->not->toBeNull()
        ->and(attr($xp, '//select[@name="filter[status]"]//option[@selected]', 'value'))->toBe('draft')
        ->and(attr($xp, '//select[@name="filter[featured]"]//option[@selected]', 'value'))->toBe('1')
        ->and(count_nodes($xp, '//input[@type="date"][@name="filter[published_at][from]"]'))->toBe(1)
        ->and(attr($xp, '//form[@data-toolbar]//input[@type="hidden"][@name="sort"]', 'value'))->toBe('views')
        ->and(attr($xp, '//form[@data-toolbar]//input[@type="hidden"][@name="dir"]', 'value'))->toBe('desc')
        ->and(count_nodes($xp, '//form[@data-toolbar]//input[@type="hidden"][@name="filter[status]"]'))->toBe(0) // the controls carry them
        ->and(count_nodes($xp, '//form[@data-toolbar]//input[@type="hidden"][@name="q"]'))->toBe(0);
});

it('shows Resetuj only while a search or filter is active, and it clears them', function () {
    seedMany(5);

    expect(count_nodes(renderItems(), '//a[@data-reset]'))->toBe(0);

    $xp = renderItems(['q' => 'x', 'sort' => 'views', 'filter' => ['status' => 'draft']]);
    expect(count_nodes($xp, '//a[@data-reset]'))->toBe(1)
        ->and(attr($xp, '//a[@data-reset]', 'href'))->toBe('http://localhost/admin/items?sort=views&dir=asc')
        ->and(text_of($xp, '//a[@data-reset]'))->toBe('Resetuj');
});

it('offers every column without a declared filter in the more-filters panel', function () {
    seedMany(5);
    $xp = renderItems(['filter' => ['views' => ['from' => '20']]]);

    expect(count_nodes($xp, '//*[@data-more-filters]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-toolbar]//button[@data-more-filters-toggle]'))->toBe(1)
        ->and(attr($xp, '//*[@data-more-filters]', 'data-open'))->not->toBeNull() // an active panel filter opens it
        ->and(attr($xp, '//*[@data-more-filters]//input[@name="filter[views][from]"]', 'value'))->toBe('20')
        ->and(count_nodes($xp, '//*[@data-more-filters]//input[@name="filter[title]"]'))->toBe(1)
        ->and(count_nodes($xp, '//*[@data-more-filters]//*[@name="filter[status]"]'))->toBe(0); // declared filters stay in the toolbar

    expect(attr(renderItems(), '//*[@data-more-filters]', 'data-open'))->toBeNull();
});

it('shows the export link only for a user who may export, with the current state', function () {
    seedMany(5);

    expect(attr(renderItems(['q' => 'it', 'page' => '1']), '//a[@data-export]', 'href'))->toBe('http://localhost/admin/tables/items/export?q=it')
        ->and(count_nodes(renderItems([], ['items.publish']), '//a[@data-export]'))->toBe(0);
});

// ------------------------------------------------------------------------------------------------------------- header

it('marks the sorted column with aria-sort and an indicator, and links the others to an ascending sort', function () {
    seedMany(5);
    $xp = renderItems(['sort' => 'views', 'dir' => 'desc']);

    expect(attr($xp, '//th[@data-column="views"]', 'aria-sort'))->toBe('descending')
        ->and(attr($xp, '//th[@data-column="title"]', 'aria-sort'))->toBe('none')
        ->and(attr($xp, '//th[@data-column="views"]//a', 'href'))->toBe('http://localhost/admin/items?sort=views&dir=asc')
        ->and(attr($xp, '//th[@data-column="title"]//a', 'href'))->toBe('http://localhost/admin/items?sort=title&dir=asc')
        ->and(text_of($xp, '//th[@data-column="views"]//span[@class="sr-only"]'))->toBe('(sortirano opadajuće)');

    $asc = renderItems(['sort' => 'views', 'dir' => 'asc']);
    expect(attr($asc, '//th[@data-column="views"]', 'aria-sort'))->toBe('ascending')
        ->and(attr($asc, '//th[@data-column="views"]//a', 'href'))->toBe('http://localhost/admin/items?sort=views&dir=desc');
});

it('has no sort link and no aria-sort on a column that cannot be sorted', function () {
    seedMany(3);
    $table = (new ItemsDefinition)->table()->columns(Column::text('note', 'Note', 'title')->sortable(false));
    $xp = renderItems([], ['items.view'], $table);

    expect(attr($xp, '//th[@data-column="note"]', 'aria-sort'))->toBeNull()->and(count_nodes($xp, '//th[@data-column="note"]//a'))->toBe(0)
        ->and(attr($xp, '//th[@scope="col"]', 'scope'))->toBe('col');
});

it('keeps the first data column in view on small screens', function () {
    seedMany(3);
    $withBulk = renderItems();
    $without = renderItems([], ['items.view'], itemsTable());

    expect(attr($withBulk, '//th[@data-column="id"]', 'class'))->toContain('sticky')->toContain('start-11')
        ->and(attr($withBulk, '//tbody//tr[1]/td[2]', 'class'))->toContain('sticky')
        ->and(attr($without, '//th[@data-column="id"]', 'class'))->toContain('start-0')
        ->and(attr($withBulk, '//div[@data-scroll]', 'class'))->toContain('overflow-x-auto');
});

// --------------------------------------------------------------------------------------------------------- selection

it('adds the checkbox column and the bulk bar only for a user who may run a bulk action', function () {
    seedMany(30);
    $xp = renderItems(['per_page' => '10']);

    expect(count_nodes($xp, '//input[@data-select-all]'))->toBe(1)
        ->and(count_nodes($xp, '//input[@data-row-select]'))->toBe(10)
        ->and(attr($xp, '//input[@data-row-select]', 'form'))->toBe('tbl-items-bulk')
        ->and(attr($xp, '//input[@data-row-select]', 'name'))->toBe('ids[]')
        ->and(attr($xp, '//form[@data-bulk-bar]', 'method'))->toBe('POST')
        ->and(attr($xp, '//form[@data-bulk-bar]', 'action'))->toBe('http://localhost/admin/tables/items/bulk')
        ->and(attr($xp, '//form[@data-bulk-bar]//input[@name="_token"]', 'type'))->toBe('hidden')
        ->and(count_nodes($xp, '//form[@data-bulk-bar]//select[@name="action"]/option[@value="publish"]'))->toBe(1)
        ->and(attr($xp, '//form[@data-bulk-bar]//option[@value="publish"]', 'data-confirm'))->toBe('Publish the selected items?')
        ->and(attr($xp, '//span[@data-selected-label]', 'data-selected-label'))->toBe('Izabrano (:count)');

    $viewer = renderItems([], ['items.view']);
    expect(count_nodes($viewer, '//form[@data-bulk-bar]//option[@value="publish"]'))->toBe(0) // no permission, no option
        ->and(count_nodes($viewer, '//form[@data-bulk-bar]//option[@value="open"]'))->toBe(1);

    $none = renderItems([], ['items.publish']); // may not even view
    expect(count_nodes($none, '//form[@data-bulk-bar]'))->toBe(0)->and(count_nodes($none, '//input[@data-select-all]'))->toBe(0);
});

it('offers "select all results" as the filter, with the filter in hidden fields and the total in the label', function () {
    seedMany(60);
    $xp = renderItems(['q' => 'Item', 'filter' => ['status' => 'draft'], 'per_page' => '10']);

    expect(attr($xp, '//input[@data-all-matching]', 'name'))->toBe('scope')
        ->and(attr($xp, '//input[@data-all-matching]', 'value'))->toBe('filter')
        ->and(text_of($xp, '//label[input[@data-all-matching]]'))->toBe('Izaberi sve rezultate (20)')
        ->and(attr($xp, '//form[@data-bulk-bar]//input[@name="q"]', 'value'))->toBe('Item')
        ->and(attr($xp, '//form[@data-bulk-bar]//input[@name="filter[status]"]', 'value'))->toBe('draft')
        ->and(count_nodes($xp, '//form[@data-bulk-bar]//input[@name="ids[]"]'))->toBe(0); // ids belong to the rows, via `form`

    // everything fits on the page: nothing to extend
    seedMany(0);
    expect(count_nodes(renderItems(['filter' => ['status' => 'draft'], 'per_page' => '100']), '//input[@data-all-matching]'))->toBe(0);
});

// ------------------------------------------------------------------------------------------------------------ actions

it('shows row actions per permission: edit as a link, delete through the confirm dialog', function () {
    seedMany(2);
    $xp = renderItems();

    expect(attr($xp, '//tbody/tr[1]//a[@data-action="edit"]', 'href'))->toBe('/items/1/edit')
        ->and(attr($xp, '//tbody/tr[1]//div[@data-action="delete"]//form[@data-confirm-form]', 'action'))->toBe('/items/1')
        ->and(attr($xp, '//tbody/tr[1]//div[@data-action="delete"]//form[@data-confirm-step]', 'action'))->toBe(route('codeart.confirm'))
        ->and(attr($xp, '//tbody/tr[1]//div[@data-action="delete"]//form[@data-confirm-form]//input[@name="_method"]', 'value'))->toBe('DELETE')
        ->and(count_nodes($xp, '//tbody/tr[1]//div[@data-action="delete"]//form[@data-confirm-form]//dialog'))->toBe(1)
        ->and(attr($xp, '//tbody/tr[1]//div[@data-action="delete"]//form[@data-confirm-form]//dialog', 'role'))->toBe('alertdialog');

    $editorOnly = renderItems([], ['items.view', 'items.edit']);
    expect(count_nodes($editorOnly, '//tbody//a[@data-action="edit"]'))->toBe(2)->and(count_nodes($editorOnly, '//tbody//form[@data-action="delete"]'))->toBe(0);

    $viewer = renderItems([], ['items.view']);
    expect(count_nodes($viewer, '//tbody//*[@data-action]'))->toBe(0)->and(count_nodes($viewer, '//th[not(@data-column) and not(input)]'))->toBe(0);
});

// ------------------------------------------------------------------------------------------------------------- footer

it('shows the range, the per-page select and numbered pagination', function () {
    seedMany(95);
    $xp = renderItems(['page' => '4', 'per_page' => '10', 'sort' => 'views']);

    expect(text_of($xp, '//p[@data-showing]'))->toBe('Prikazano 31–40 od 95')
        ->and(count_nodes($xp, '//select[@name="per_page"]/option'))->toBe(4)
        ->and(attr($xp, '//select[@name="per_page"]//option[@selected]', 'value'))->toBe('10')
        ->and(attr($xp, '//nav[@data-pagination]', 'aria-label'))->toBe('Straničenje')
        ->and(attr($xp, '//nav//*[@aria-current="page"]', 'aria-label'))->toBe('Strana 4')
        ->and(text_of($xp, '//nav//*[@aria-current="page"]'))->toBe('4')
        ->and(array_map(fn ($n) => $n->textContent, iterator_to_array($xp->query('//nav//a[number(.) = number(.)]'))))->toBe(['2', '3', '5', '6'])
        ->and(attr($xp, '//nav//a[@rel="first"]', 'href'))->toBe('http://localhost/admin/items?sort=views&dir=asc&per_page=10')
        ->and(attr($xp, '//nav//a[@rel="last"]', 'href'))->toBe('http://localhost/admin/items?sort=views&dir=asc&per_page=10&page=10')
        ->and(attr($xp, '//nav//a[@rel="prev"]', 'href'))->toContain('page=3')
        ->and(attr($xp, '//nav//a[@rel="next"]', 'href'))->toContain('page=5')
        ->and(attr($xp, '//form[.//select[@name="per_page"]]//input[@name="sort"]', 'value'))->toBe('views')
        ->and(count_nodes($xp, '//form[.//select[@name="per_page"]]//input[@name="page"]'))->toBe(0);
});

it('disables first and previous on the first page and next and last on the last', function () {
    seedMany(60);

    $first = renderItems(['per_page' => '25']);
    expect(count_nodes($first, '//nav//a[@rel="first" or @rel="prev"]'))->toBe(0)->and(count_nodes($first, '//nav//a[@rel="next"]'))->toBe(1);

    $last = renderItems(['per_page' => '25', 'page' => '3']);
    expect(count_nodes($last, '//nav//a[@rel="next" or @rel="last"]'))->toBe(0)->and(text_of($last, '//p[@data-showing]'))->toBe('Prikazano 51–60 od 60');

    expect(count_nodes(renderItems(['per_page' => '100']), '//nav[@data-pagination]'))->toBe(0); // one page, no pagination
});

// -------------------------------------------------------------------------------------------------------------- states

it('shows an empty state without a toolbar when the table has no rows at all', function () {
    $xp = renderItems();

    expect(text_of($xp, '//h3'))->toBe('Još nema podataka')->and(count_nodes($xp, '//form[@data-toolbar]'))->toBe(0)->and(count_nodes($xp, '//table'))->toBe(0);
});

it('shows a "no results" state with the toolbar and a reset button when the filter matches nothing', function () {
    seedMany(5);
    $xp = renderItems(['q' => 'zzzz']);

    expect(text_of($xp, '//h3'))->toBe('Nema rezultata')->and(count_nodes($xp, '//form[@data-toolbar]'))->toBe(1)
        ->and(count_nodes($xp, '//table'))->toBe(0)
        ->and(attr($xp, '//div[@data-no-results]//a', 'href'))->toBe('http://localhost/admin/items');
});

// --------------------------------------------------------------------------------------------------------------- cells

it('draws every cell type', function () {
    DB::table('tbl_items')->insert(['title' => 'First item', 'status' => 'published', 'category_id' => 2, 'views' => 1234567, 'featured' => 1, 'published_at' => '2026-03-05 14:30:00']);
    $table = DataTable::make('cells', TableItem::query()->with('category'))->columns(
        Column::text('title', 'Text'),
        Column::number('views', 'Number'),
        Column::date('published_at', 'Date'),
        Column::boolean('featured', 'Bool'),
        Column::status('status', 'Status', ['published' => ['Objavljeno', 'success']]),
        Column::badge('category_id', 'Badge')->using(fn ($i) => 'x')->options(['x' => ['Blog', 'primary']]),
        Column::avatar('title', 'Avatar')->using(fn ($i) => ['name' => 'Pavle Nikitović', 'subtitle' => 'admin']),
        Column::titleSubtitle('title', 'TS')->using(fn ($i) => ['title' => $i->title, 'subtitle' => 'sub', 'url' => '/items/1']),
        Column::thumbnail('thumb', 'Thumb')->using(fn () => '/img/a.jpg'),
        Column::languageChips('langs', 'Lang')->using(fn () => ['sr' => true, 'en' => false]),
    );
    $xp = dom(Blade::render('<x-codeart::table :result="$result" />', ['result' => $table->resolve(tableRequest())]));
    $cell = fn (string $key) => '//tbody/tr[1]/td['.(array_search($key, ['text', 'number', 'date', 'bool', 'status', 'badge', 'avatar', 'ts', 'thumb', 'lang']) + 1).']';

    expect(text_of($xp, $cell('text')))->toBe('First item')
        ->and(text_of($xp, $cell('number')))->toBe('1.234.567')
        ->and(attr($xp, $cell('number').'//span', 'class'))->toContain('tabular-nums')
        ->and(attr($xp, $cell('date').'//time', 'datetime'))->toStartWith('2026-03-05T14:30:00')
        ->and(text_of($xp, $cell('date')))->toBe('05.03.2026. 14:30')
        ->and(text_of($xp, $cell('bool')))->toBe('Da')
        ->and(attr($xp, $cell('status').'//span[@data-tone]', 'data-tone'))->toBe('success')
        ->and(text_of($xp, $cell('status')))->toBe('Objavljeno')
        ->and(attr($xp, $cell('badge').'//span[@data-tone]', 'data-tone'))->toBe('primary')
        ->and(text_of($xp, $cell('avatar')))->toBe('PN Pavle Nikitović admin')
        ->and(attr($xp, $cell('avatar').'//span[@aria-hidden="true"]', 'class'))->toContain('rounded-full')
        ->and(attr($xp, $cell('ts').'//a', 'href'))->toBe('/items/1')
        ->and(text_of($xp, $cell('ts')))->toBe('First item sub')
        ->and(attr($xp, $cell('thumb').'//img', 'src'))->toBe('/img/a.jpg')
        ->and(attr($xp, $cell('thumb').'//img', 'loading'))->toBe('lazy')
        ->and(attr($xp, $cell('lang').'//span[@data-lang="sr"]', 'data-tone'))->toBe('primary')
        ->and(attr($xp, $cell('lang').'//span[@data-lang="en"]', 'class'))->toContain('opacity-60')
        ->and(text_of($xp, $cell('lang').'//span[@data-lang="en"]'))->toBe('en (nema prevoda)');
});

it('shows a dash for empty values and an initials fallback for an avatar without a name', function () {
    DB::table('tbl_items')->insert(['title' => '', 'status' => 'draft']);
    $table = DataTable::make('cells', TableItem::query())->columns(Column::text('title', 'T'), Column::date('published_at', 'D'), Column::number('category_id', 'N'), Column::avatar('title', 'A'));
    $xp = dom(Blade::render('<x-codeart::table :result="$result" />', ['result' => $table->resolve(tableRequest())]));

    expect(text_of($xp, '//tbody/tr[1]/td[1]'))->toBe('—')->and(text_of($xp, '//tbody/tr[1]/td[2]'))->toBe('—')->and(text_of($xp, '//tbody/tr[1]/td[3]'))->toBe('—')
        ->and(text_of($xp, '//tbody/tr[1]/td[4]//span[@aria-hidden="true"]'))->toBe('?');
});

it('escapes cell text', function () {
    DB::table('tbl_items')->insert(['title' => '<script>alert(1)</script>', 'status' => 'draft']);
    $html = Blade::render('<x-codeart::table :result="$result" />', ['result' => itemsTable()->resolve(tableRequest())]);

    expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

// ------------------------------------------------------------------------------------------------- no N+1, no colours

it('runs the same number of queries for 10 rows as for 100', function () {
    $user = tableUser(['items.view', 'items.publish', 'items.edit', 'items.delete']);
    $count = function (int $rows) use ($user): int {
        DB::table('tbl_items')->delete();
        seedMany($rows);
        $user = $user->fresh(); // permissions are loaded lazily, once per request
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = (new ItemsDefinition)->table()->resolve(tableRequest(['per_page' => '100']), $user);
        Blade::render('<x-codeart::table :result="$result" />', ['result' => $result]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $few = $count(10);
    $many = $count(100);

    expect($many)->toBe($few)->and($few)->toBeLessThan(8); // count, page, categories, permissions: independent of the rows
});

it('uses semantic colour utilities only in the table views', function () {
    $raw = '/(#[0-9a-fA-F]{3,8}\b|rgb\(|hsl\(|\b(?:bg|text|border|ring|fill|stroke)-(?:white|black|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)(?:-\d+)?\b)/';
    foreach (array_merge([__DIR__.'/../../../resources/views/components/table.blade.php'], glob(__DIR__.'/../../../resources/views/components/table/*.php')) as $file) {
        expect(preg_match($raw, (string) file_get_contents($file)))->toBe(0, basename($file));
    }
});
