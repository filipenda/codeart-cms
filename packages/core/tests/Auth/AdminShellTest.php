<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Admin\Navigation\AdminMenu;
use Codeart\Core\Admin\Search\SearchProvider;
use Codeart\Core\Admin\Search\SearchRegistry;
use Codeart\Core\Admin\Search\SearchResult;
use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Settings\Settings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
});

function enableShell(): void
{
    useModules(['shell' => '1.0.0']);
    app(EnableModule::class)('shell');
}

function dashboard($test, $user, string $uri = '/admin')
{
    return $test->actingAs($user, 'admin')->get($uri);
}

function menuLabels($html): array
{
    $xp = dom($html);
    $labels = [];
    foreach ($xp->query('//aside//nav//a | //aside//nav//button') as $node) {
        $labels[] = $node->getAttribute('aria-label') ?: trim(preg_replace('/\s+/', ' ', $node->textContent));
    }

    return $labels;
}

// ---- access ----------------------------------------------------------------------------------------------------

it('sends guests to the sign-in screen and back to the page after signing in', function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $user = staff('editor');
    $this->post('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertRedirect('/admin');
});

it('answers the themed 403 page to a signed-in user who may not enter the admin', function () {
    $response = dashboard($this, staff('member'));

    $response->assertForbidden()->assertSee(__('codeart::auth.denied.forbidden'));
    expect(count_nodes(dom($response->getContent()), '//aside'))->toBe(0);
});

it('lets editor, admin and super-admin see the dashboard', function (string $role) {
    dashboard($this, staff($role))->assertOk();
})->with(['super-admin', 'admin', 'editor']);

it('sends a user whose role requires two-factor to the setup page, without a loop', function () {
    app(Settings::class)->set('security.2fa.mode', 'required');
    $user = staff('admin');

    dashboard($this, $user)->assertRedirect(route('admin.two-factor.setup'));

    // The setup page itself is reachable (it asks for the password first); it does not send us back to /admin.
    $setup = $this->actingAs($user, 'admin')->get(route('admin.two-factor.setup'));
    expect($setup->headers->get('Location'))->not->toBe(url('/admin'))->not->toBe(route('admin.two-factor.setup'));

    // Enrolled: the dashboard opens.
    $user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();
    dashboard($this, $user->fresh())->assertOk();
});

// ---- layout ----------------------------------------------------------------------------------------------------

it('renders the shell with skip link, landmarks, theme and language switchers and a POST logout', function () {
    $html = dashboard($this, staff('editor'))->assertOk()->getContent();
    $xp = dom($html);

    expect(attr($xp, '//a[@href="#main"]', 'class'))->toContain('sr-only')
        ->and(count_nodes($xp, '//header'))->toBe(1)
        ->and(count_nodes($xp, '//aside//nav'))->toBe(1)
        ->and(count_nodes($xp, '//main[@id="main"]'))->toBe(1)
        ->and(count_nodes($xp, '//div[@role="group"]//button[@aria-pressed]'))->toBe(3)             // light / dark / auto
        ->and(count_nodes($xp, '//form[contains(@action, "/admin/locale")]//button[@name="locale"]'))->toBe(3) // sr, sr-Cyrl, en
        ->and(count_nodes($xp, '//form[@method="POST"][contains(@action, "/admin/logout")]//input[@name="_token"]'))->toBe(1)
        ->and($html)->toContain("localStorage.getItem('codeart.theme')")
        ->and($html)->toContain('Ctrl K');
});

it('logs out with POST only', function () {
    $this->actingAs(staff('editor'), 'admin')->get('/admin/logout')->assertStatus(405);
});

it('shows the user, the highest role and the dashboard link as the current page', function () {
    $user = staff('admin', ['name' => 'Mila Petrović']);
    $user->assignRole('editor');
    $xp = dom(dashboard($this, $user)->getContent());

    expect(text_of($xp, '//header'))->toContain('Mila Petrović')->toContain('Administrator')
        ->and(attr($xp, '//aside//a[@aria-current="page"]', 'href'))->toBe(route('admin.dashboard'))
        ->and(text_of($xp, '//aside'))->toContain('CMS ');
});

it('shows the two-step link only while two-step verification is offered', function () {
    $user = staff('editor');
    $link = fn () => count_nodes(dom(dashboard($this, $user->fresh())->getContent()), '//header//a[contains(@href, "two-factor-setup")]');

    app(Settings::class)->set('security.2fa.mode', 'off');
    expect($link())->toBe(0);
    app(Settings::class)->set('security.2fa.mode', 'optional');
    expect($link())->toBe(1);
});

it('welcomes the user by first name and shows the empty state when no widget is allowed', function () {
    $html = dashboard($this, staff('editor', ['name' => 'Mila Petrović']))->getContent();

    expect($html)->toContain(__('codeart::admin.dashboard.welcome', ['name' => 'Mila']))
        ->and($html)->toContain(__('codeart::admin.dashboard.empty_title'))
        ->and(count_nodes(dom($html), '//h1'))->toBe(1);
});

it('has no color literals or palette utilities anywhere in the repository', function () {
    $script = __DIR__.'/../../../../tools/check-colors.php';
    if (! is_file($script)) {
        $this->markTestSkipped('Not running inside the monorepo.');
    }
    require_once $script;

    expect(codeart_find_color_violations(dirname(__DIR__, 4)))->toBe([]);
});

// ---- menu ------------------------------------------------------------------------------------------------------

it('filters menu items by permission and hides empty groups and pages that do not exist', function () {
    enableShell();
    $viewer = staff('editor')->givePermissionTo('shell.view');
    $labels = menuLabels(dashboard($this, $viewer->fresh())->getContent());

    expect($labels)->toContain('Shell content')->toContain('Shell list')
        ->not->toContain('Shell settings')   // no shell.edit
        ->not->toContain('Ghost group')      // its only child is not allowed: empty group
        ->not->toContain('Missing page');    // route does not exist

    $editor = staff('editor')->givePermissionTo(['shell.view', 'shell.edit']);
    expect(menuLabels(dashboard($this, $editor->fresh())->getContent()))
        ->toContain('Shell settings')->toContain('Ghost group')->toContain('Ghost secret');
});

it('lists the group before its children and marks the open group and current child', function () {
    enableShell();
    $user = staff('editor')->givePermissionTo('shell.view');
    $html = dashboard($this, $user->fresh(), '/shell-list')->getContent(); // plain page: no layout
    expect($html)->toBe('list');

    $tree = app(AdminMenu::class)->tree($user->fresh(), 'shell.index');
    $group = collect($tree)->firstWhere('label', 'Shell content');
    expect($group->open())->toBeTrue()->and($group->children[0]->active)->toBeTrue();
});

it('shows nothing of a module that is not enabled', function () {
    useModules(['shell' => '1.0.0']); // installed, never enabled: its provider does not boot
    $user = staff('editor');

    expect(menuLabels(dashboard($this, $user)->getContent()))->not->toContain('Shell content')->not->toContain('Shell list');
});

it('fills the command palette with the visible items only', function () {
    enableShell();
    $user = staff('editor')->givePermissionTo('shell.view');
    $html = dashboard($this, $user->fresh())->getContent();
    preg_match('#<script type="application/json" id="codeart-palette-items">(.*?)</script>#s', $html, $m);
    $items = json_decode($m[1], true);

    expect(array_column($items, 'label'))->toContain('Shell list')->not->toContain('Shell settings')->not->toContain('Ghost secret')
        ->and(collect($items)->firstWhere('label', 'Shell list')['group'])->toBe('Shell content');
});

it('lets a module add its own search provider', function () {
    app(SearchRegistry::class)->add(ExtraSearch::class);
    $items = app(SearchRegistry::class)->results(null);

    expect(array_column($items, 'label'))->toContain('Extra result');
});

class ExtraSearch implements SearchProvider
{
    public function results(?Authenticatable $user): array
    {
        return [new SearchResult('Extra result', 'https://example.test/x', 'star', 'Extra')];
    }
}

// ---- widgets ---------------------------------------------------------------------------------------------------

it('renders widgets sorted by order, and a failing widget becomes an error card without breaking the rest', function () {
    enableShell();
    Log::spy();
    $user = staff('editor')->givePermissionTo(['shell.view', 'shell.edit']);
    $html = dashboard($this, $user->fresh())->assertOk()->getContent();
    $xp = dom($html);

    $keys = [];
    foreach ($xp->query('//*[@data-widget]') as $node) {
        $keys[] = $node->getAttribute('data-widget');
    }
    expect($keys)->toBe(['shell.early', 'shell.broken', 'core.quick-actions', 'shell.late'])
        ->and(attr($xp, '//*[@data-widget="shell.broken"]', 'data-failed'))->not->toBeNull()
        ->and(text_of($xp, '//*[@data-widget="shell.broken"]'))->toContain(__('codeart::admin.widgets.error_title'))
        ->and($html)->not->toContain('secret failure detail')
        ->and(text_of($xp, '//*[@data-widget="shell.late"]'))->toContain('hello widget')
        ->and(attr($xp, '//*[@data-widget="shell.early"]', 'class'))->toContain('md:col-span-12')
        ->and(attr($xp, '//*[@data-widget="shell.broken"]', 'class'))->toContain('lg:col-span-3');

    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'shell.broken'))->once();
});

it('collects quick actions the user may use into one widget', function () {
    enableShell();
    $only = staff('editor')->givePermissionTo('shell.view');
    expect(count_nodes(dom(dashboard($this, $only->fresh())->getContent()), '//*[@data-widget="core.quick-actions"]'))->toBe(0);

    $both = staff('editor')->givePermissionTo(['shell.view', 'shell.edit']);
    $xp = dom(dashboard($this, $both->fresh())->getContent());
    expect(attr($xp, '//*[@data-widget="core.quick-actions"]//a', 'href'))->toBe(route('shell.create'))
        ->and(text_of($xp, '//*[@data-widget="core.quick-actions"]'))->toContain('New shell thing');
});

it('shows recent activity (last 8, with who and when) to those who may view users', function () {
    $admin = staff('admin', ['name' => 'Ana Admin']);
    foreach (range(1, 10) as $i) {
        activity()->causedBy($admin)->log("Entry number {$i}");
    }
    $xp = dom(dashboard($this, $admin->fresh())->getContent());
    $widget = '//*[@data-widget="core.recent-activity"]';

    expect(count_nodes($xp, $widget.'//li'))->toBe(8)
        ->and(text_of($xp, $widget))->toContain('Entry number 10')->toContain('Ana Admin')->not->toContain('Entry number 1 ')
        ->and(count_nodes($xp, $widget.'//time'))->toBe(8);

    expect(Activity::count())->toBeGreaterThanOrEqual(10);
    expect(count_nodes(dom(dashboard($this, staff('editor'))->getContent()), $widget))->toBe(0);
});

it('shows system status to admins only, with real checks', function () {
    $xp = dom(dashboard($this, staff('admin'))->getContent());
    $widget = '//*[@data-widget="core.system-status"]';

    expect(text_of($xp, $widget))->toContain(__('codeart::admin.widgets.check.database'))->toContain(PHP_VERSION)
        ->and(count_nodes(dom(dashboard($this, staff('editor'))->getContent()), $widget))->toBe(0);
});
