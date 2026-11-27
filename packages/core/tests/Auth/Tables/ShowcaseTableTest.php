<?php

require_once __DIR__.'/../../Support/dom.php';

use Codeart\Core\Admin\Demo\ActivityLogTable;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tables\TableRegistry;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    $this->app['env'] = 'local';
});

function logEntries(int $n): void
{
    $rows = [];
    for ($i = 1; $i <= $n; $i++) {
        $rows[] = ['log_name' => $i % 2 ? 'auth' : 'tables', 'description' => "Entry number {$i}", 'event' => 'demo.event', 'created_at' => now()->subMinutes($i), 'updated_at' => now()];
    }
    DB::table('activity_log')->insert($rows);
}

it('shows the activity log as a data table on the showcase for a super-admin', function () {
    logEntries(60);
    $xp = dom($this->actingAs(staff('super-admin'), 'admin')->get('/admin/_ui')->assertOk()->getContent());

    expect(count_nodes($xp, '//div[@data-table="ui-activity"]'))->toBe(1)
        ->and(count_nodes($xp, '//div[@data-table="ui-activity"]//tbody/tr'))->toBe(25)
        ->and(text_of($xp, '//div[@data-table="ui-activity"]//p[@data-showing]'))->toContain('od 6')
        ->and(attr($xp, '//div[@data-table="ui-activity"]//th[@data-column="created_at"]', 'aria-sort'))->toBe('descending')
        ->and(count_nodes($xp, '//div[@data-table="ui-activity"]//form[@data-bulk-bar]'))->toBe(1)
        ->and(count_nodes($xp, '//div[@data-table="ui-activity"]//a[@data-export]'))->toBe(1);
});

it('searches, sorts, filters and pages the demo table through the query string', function () {
    logEntries(60);
    $user = staff('super-admin');
    $get = fn (string $q) => dom($this->actingAs($user, 'admin')->get('/admin/_ui'.$q)->assertOk()->getContent());

    expect(count_nodes($get('?q=Entry+number+60'), '//div[@data-table]//tbody/tr[contains(.,"Entry number 60")]'))->toBe(1)
        ->and(count_nodes($get('?q=Entry+number+60'), '//div[@data-table]//tbody/tr'))->toBeLessThan(4)
        ->and(count_nodes($get('?filter[log_name]=tables&per_page=10'), '//div[@data-table]//tbody/tr'))->toBe(10)
        ->and(count_nodes($get('?sort=id&dir=asc&page=2'), '//div[@data-table]//tbody/tr'))->toBe(25);
    $this->actingAs($user, 'admin')->get('/admin/_ui?page=99')->assertRedirect();
});

it('runs the no-op bulk action on the filter and reports a toast', function () {
    logEntries(30);
    $user = staff('super-admin');

    $response = $this->withSession(['_token' => 't'])->actingAs($user, 'admin')->from('/admin/_ui')->post(route('codeart.tables.bulk', ['table' => ActivityLogTable::KEY]), ['_token' => 't', 'action' => 'review', 'scope' => 'filter', 'filter' => ['log_name' => 'auth']]);

    $response->assertRedirect('/admin/_ui');
    expect($response->getSession()->get('toast.message'))->toContain('Završeno: 15');
});

it('exports the demo table as CSV for a super-admin only, and only in the local environment', function () {
    logEntries(5);

    $csv = $this->actingAs(staff('super-admin'), 'admin')->get(route('codeart.tables.export', ['table' => ActivityLogTable::KEY]))->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('Entry number 1');

    $this->actingAs(staff('admin'), 'admin')->get(route('codeart.tables.export', ['table' => ActivityLogTable::KEY]))->assertForbidden();

    $this->app['env'] = 'production';
    $this->actingAs(staff('super-admin'), 'admin')->get(route('codeart.tables.export', ['table' => ActivityLogTable::KEY]))->assertNotFound();
    expect(app(TableRegistry::class)->has(ActivityLogTable::KEY))->toBeTrue();
});
