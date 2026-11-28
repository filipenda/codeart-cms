<?php

require_once __DIR__.'/../../Support/tables.php';
require_once __DIR__.'/../../Support/dom.php';

use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    makeItemTables();
    app(TableRegistry::class)->register('items', ItemsDefinition::class);
    ItemsDefinition::$seen = [];
    ItemsDefinition::$handler = null;
    seedMany(6);
});

function confirmUser(array $permissions = ['items.view', 'items.publish', 'items.remove']): AdminTestUser
{
    $user = staff('editor');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'admin');
        $user->givePermissionTo($permission);
    }

    return $user->fresh();
}

function removeBulk($test, AdminTestUser $user, array $data, bool $json = false)
{
    $test = $test->actingAs($user, 'admin')->from('/admin/items');

    return $json ? $test->postJson(route('codeart.tables.bulk', ['table' => 'items']), $data) : $test->post(route('codeart.tables.bulk', ['table' => 'items']), $data);
}

function issueBulkToken($test, AdminTestUser $user, array $data): string
{
    return removeBulk($test, $user, $data, true)->assertOk()->json('token');
}

// ----------------------------------------------------------------------------------------- destructive bulk action

it('shows the confirmation step instead of running a destructive bulk action without a token', function () {
    $response = removeBulk($this, confirmUser(), ['action' => 'remove', 'ids' => ['1', '2', '3']]);

    $response->assertOk();
    $xp = dom($response->getContent());
    expect(ItemsDefinition::$seen)->toBe([])
        ->and(text_of($xp, '//*[@data-confirm-text]'))->toBe('Remove the selected items?')
        ->and(text_of($xp, '//*[@data-confirm-count]'))->toBe('3')
        ->and(count_nodes($xp, '//form[@data-confirm-run]//input[@name="confirmation"][@type="hidden"]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-confirm-run]//input[@name="ids[0]"][@value="1"]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-confirm-run]//input[@name="action"][@value="remove"]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-confirm-run]//input[@name="_token"]'))->toBe(1);
});

it('runs a destructive bulk action once with the token the confirmation step issued', function () {
    $user = confirmUser();
    $data = ['action' => 'remove', 'ids' => ['1', '2']];
    $token = issueBulkToken($this, $user, $data);

    removeBulk($this, $user, $data + ['confirmation' => $token])->assertRedirect('/admin/items');
    expect(ItemsDefinition::$seen)->toBe([1, 2]);

    // reused
    ItemsDefinition::$seen = [];
    expect(removeBulk($this, $user, $data + ['confirmation' => $token])->getContent())->toContain('data-confirm-page')
        ->and(ItemsDefinition::$seen)->toBe([]);
});

it('rejects tokens of another user, action, selection, table state, an expired, a tampered and a missing one', function () {
    $user = confirmUser();
    $other = confirmUser();
    $data = ['action' => 'remove', 'ids' => ['1', '2']];

    $cases = [
        'other user' => fn () => [$other, $data, issueBulkToken($this, $user, $data)],
        'other selection' => fn () => [$user, ['action' => 'remove', 'ids' => ['1', '3']], issueBulkToken($this, $user, $data)],
        'more ids' => fn () => [$user, ['action' => 'remove', 'ids' => ['1', '2', '3']], issueBulkToken($this, $user, $data)],
        'ids instead of the filter' => fn () => [$user, ['action' => 'remove', 'scope' => 'filter'], issueBulkToken($this, $user, $data)],
        'tampered' => fn () => [$user, $data, substr(issueBulkToken($this, $user, $data), 0, -4).'AAAA'],
        'garbage' => fn () => [$user, $data, 'not-a-token'],
        'empty' => fn () => [$user, $data, ''],
    ];
    foreach ($cases as $name => $make) {
        [$who, $payload, $token] = $make();
        removeBulk($this, $who, $payload + ['confirmation' => $token]);
        expect(ItemsDefinition::$seen)->toBe([], $name);
    }

    // another action: a token for `remove` never starts another destructive action (the scope names the action)
    $tokens = app(ConfirmationTokens::class);
    $foreign = $tokens->issue($user, 'bulk:items:other', ['ids' => ['1', '2']]);
    removeBulk($this, $user, $data + ['confirmation' => $foreign]);
    expect(ItemsDefinition::$seen)->toBe([]);

    // expired
    $expired = issueBulkToken($this, $user, $data);
    $this->travel(ConfirmationTokens::TTL + 5)->seconds();
    removeBulk($this, $user, $data + ['confirmation' => $expired]);
    expect(ItemsDefinition::$seen)->toBe([]);
});

it('binds the token of "all matching" to the filter and describes it', function () {
    $user = confirmUser();
    $data = ['action' => 'remove', 'scope' => 'filter', 'filter' => ['status' => 'draft']];

    $page = removeBulk($this, $user, $data);
    $xp = dom($page->getContent());
    expect(text_of($xp, '//*[@data-confirm-count]'))->toBe('2')
        ->and(text_of($xp, '//*[@data-confirm-filter]'))->toContain('Status');

    $token = issueBulkToken($this, $user, $data);
    removeBulk($this, $user, ['filter' => ['status' => 'published']] + $data + ['confirmation' => $token]);
    expect(ItemsDefinition::$seen)->toBe([]);

    removeBulk($this, $user, $data + ['confirmation' => issueBulkToken($this, $user, $data)]);
    expect(ItemsDefinition::$seen)->toHaveCount(2);
});

it('answers JSON with the texts, the count and a token for the dialog', function () {
    $json = removeBulk($this, confirmUser(), ['action' => 'remove', 'ids' => ['1', '2']], true)->assertOk()->json();

    expect($json['text'])->toBe('Remove the selected items?')
        ->and($json['count'])->toBe(2)
        ->and($json['token'])->toBeString()->not->toBeEmpty()
        ->and(ItemsDefinition::$seen)->toBe([]);
});

it('leaves non-destructive bulk actions unchanged and still enforces permissions', function () {
    $user = confirmUser();
    removeBulk($this, $user, ['action' => 'publish', 'ids' => ['1', '2']])->assertRedirect('/admin/items');
    expect(ItemsDefinition::$seen)->toBe([1, 2]);

    // no permission: 403 and no token is ever issued
    $viewer = confirmUser(['items.view']);
    removeBulk($this, $viewer, ['action' => 'remove', 'ids' => ['1']])->assertForbidden();
    removeBulk($this, $viewer, ['action' => 'remove', 'ids' => ['1']], true)->assertForbidden();
    $token = issueBulkToken($this, $user, ['action' => 'remove', 'ids' => ['1']]);
    removeBulk($this, $viewer, ['action' => 'remove', 'ids' => ['1'], 'confirmation' => $token])->assertForbidden();
    expect(ItemsDefinition::$seen)->toBe([1, 2]);
});

// ---------------------------------------------------------------------------------------- row actions and confirm

it('has no immediate form to the target in the confirm component: the trigger posts to the confirmation step', function () {
    $this->actingAs(confirmUser(), 'admin');
    view()->share('errors', new ViewErrorBag);
    $xp = dom(Blade::render('<x-codeart::confirm action="/things/1" method="DELETE" title="Delete it?" text="Gone." confirm="Yes">Delete</x-codeart::confirm>'));

    expect(attr($xp, '//form[@data-confirm-step]', 'action'))->toBe(route('codeart.confirm'))
        ->and(count_nodes($xp, '//form[@data-confirm-step]//button[@data-confirm-trigger]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-confirm-step]//input[@name="_method"]'))->toBe(0)
        ->and(attr($xp, '//form[@data-confirm-form]//input[@name="_method"]', 'value'))->toBe('DELETE')
        ->and(count_nodes($xp, '//form[@data-confirm-form]//button[@data-confirm-trigger]'))->toBe(0); // the only button outside the dialog is in the step form
});

it('shows a confirmation page for a row action, then the confirmed request passes the middleware once', function () {
    Route::middleware(['web', 'auth:admin', 'admin.access'])->prefix('admin')->group(function () {
        Route::delete('things/{id}', fn (string $id) => redirect('/admin/done')->with('deleted', $id))->middleware('codeart.confirmed')->name('things.destroy');
    });
    $user = confirmUser();
    $tokens = app(ConfirmationTokens::class);
    $target = '/admin/things/7';
    $step = [
        'target' => $target, 'verb' => 'DELETE', 'title' => 'Delete', 'text' => 'Gone.', 'confirm' => 'Yes', 'back' => '/admin/items',
        'sig' => $tokens->sign($target, 'DELETE', 'Delete', 'Gone.', 'Yes'),
    ];

    // directly: refused, nothing runs
    $this->actingAs($user, 'admin')->from('/admin/items')->delete($target)->assertRedirect('/admin/items')->assertSessionMissing('deleted');
    $this->actingAs($user, 'admin')->from('/admin/items')->delete($target)->assertSessionHas('toast.type', 'danger');

    // the step page (no JS) carries the token and the spoofed method
    $page = $this->actingAs($user, 'admin')->post(route('codeart.confirm'), $step)->assertOk();
    $xp = dom($page->getContent());
    expect(attr($xp, '//form[@action="'.$target.'"]//input[@name="_method"]', 'value'))->toBe('DELETE');
    $token = attr($xp, '//form[@data-confirm-run]//input[@name="confirmation"]', 'value');

    $this->actingAs($user, 'admin')->delete($target, ['confirmation' => $token])->assertRedirect('/admin/done')->assertSessionHas('deleted', '7');
    $this->actingAs($user, 'admin')->from('/admin/items')->delete($target, ['confirmation' => $token])->assertSessionMissing('deleted'); // reused

    // another target and another user
    $json = $this->actingAs($user, 'admin')->postJson(route('codeart.confirm'), $step)->assertOk()->json('token');
    $this->actingAs($user, 'admin')->from('/admin/items')->delete('/admin/things/8', ['confirmation' => $json])->assertSessionMissing('deleted');
    $this->actingAs(confirmUser(), 'admin')->from('/admin/items')->delete($target, ['confirmation' => $json])->assertSessionMissing('deleted');
    $this->actingAs($user, 'admin')->delete($target, ['confirmation' => $json])->assertSessionHas('deleted', '7');
});

it('refuses an unsigned or altered confirmation step', function () {
    $user = confirmUser();
    $step = ['target' => 'https://evil.example/x', 'verb' => 'DELETE', 'title' => 'T', 'text' => 'x', 'confirm' => 'Y', 'sig' => 'nope'];

    $this->actingAs($user, 'admin')->post(route('codeart.confirm'), $step)->assertForbidden();
    $signed = $step + [];
    $signed['sig'] = app(ConfirmationTokens::class)->sign('/admin/things/1', 'DELETE', 'T', 'x', 'Y');
    $this->actingAs($user, 'admin')->post(route('codeart.confirm'), $signed)->assertForbidden(); // target changed after signing
});

it('needs a signed-in staff user for the confirmation step', function () {
    $this->post(route('codeart.confirm'), [])->assertRedirect();
});

it('keeps window.confirm out of the table, the confirm component and the built script', function () {
    $files = [
        __DIR__.'/../../../resources/views/components/table.blade.php',
        __DIR__.'/../../../resources/views/components/confirm.blade.php',
        __DIR__.'/../../../resources/js/app.js',
        __DIR__.'/../../../resources/dist/codeart.js',
    ];
    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain('window.confirm')->not->toMatch('/(?<![\w.])confirm\(/');
    }
});

it('puts the destructive flag and the return address in the bulk form', function () {
    $user = confirmUser();
    view()->share('errors', new ViewErrorBag);
    $result = app(TableRegistry::class)->get('items')->resolve(tableRequest(), $user);
    $html = Blade::render('<x-codeart::table :result="$result" label="Items" />', ['result' => $result]);
    $xp = dom($html);

    expect(attr($xp, '//form[@data-bulk-bar]//option[@value="remove"]', 'data-destructive'))->toBe('1')
        ->and(attr($xp, '//form[@data-bulk-bar]//option[@value="publish"]', 'data-destructive'))->toBeNull()
        ->and(count_nodes($xp, '//form[@data-bulk-bar]//input[@name="return"]'))->toBe(1)
        ->and(count_nodes($xp, '//dialog[@data-bulk-dialog]'))->toBe(1);
});
