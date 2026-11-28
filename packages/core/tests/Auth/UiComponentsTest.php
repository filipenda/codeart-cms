<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    view()->share('errors', new ViewErrorBag);
});

function render(string $blade, array $data = []): DOMXPath
{
    return dom(Blade::render($blade, $data));
}

function oldInput(array $input): void
{
    $store = app('session.store');
    $store->put('_old_input', $input);
    request()->setLaravelSession($store);
}

function bag(string $field = 'f'): MessageBag
{
    return new MessageBag([$field => ['That is wrong']]);
}

// ---------------------------------------------------------------------------------------------------------- button

it('renders every button variant and size with semantic classes only', function (string $variant, string $expected) {
    $xp = render('<x-codeart::button variant="'.$variant.'" size="md" type="button">Go</x-codeart::button>');

    expect(attr($xp, '//button', 'class'))->toContain($expected)->toContain('h-11')->toContain('focus-visible:outline-focus')
        ->and(attr($xp, '//button', 'type'))->toBe('button');
})->with([['primary', 'bg-primary'], ['secondary', 'bg-surface'], ['ghost', 'bg-transparent'], ['danger', 'bg-danger']]);

it('has three sizes', function (string $size, string $height) {
    expect(attr(render('<x-codeart::button size="'.$size.'">Go</x-codeart::button>'), '//button', 'class'))->toContain($height);
})->with([['sm', 'h-9'], ['md', 'h-11'], ['lg', 'h-13']]);

it('shows the loading state and the real disabled attribute', function () {
    $loading = render('<x-codeart::button :loading="true">Save</x-codeart::button>');
    $disabled = render('<x-codeart::button :disabled="true">Save</x-codeart::button>');

    expect(attr($loading, '//button', 'aria-busy'))->toBe('true')
        ->and(attr($loading, '//button', 'aria-disabled'))->toBe('true')
        ->and(attr($loading, '//button//svg[contains(@class,"animate-spin")]', 'hidden'))->toBeNull()
        ->and(attr($disabled, '//button', 'disabled'))->not->toBeNull()
        ->and(attr(render('<x-codeart::button>Save</x-codeart::button>'), '//button//svg[contains(@class,"animate-spin")]', 'hidden'))->not->toBeNull();
});

it('renders icons on both sides and as a link', function () {
    $xp = render('<x-codeart::button icon-left="plus" icon="arrow-right">Go</x-codeart::button>');
    $link = render('<x-codeart::button href="/x" :disabled="true">Go</x-codeart::button>');

    expect(count_nodes($xp, '//button/svg[contains(@class,"size-5")]'))->toBeGreaterThanOrEqual(2)
        ->and(count_nodes($link, '//a[@href="#"][@aria-disabled="true"][@tabindex="-1"]'))->toBe(1)
        ->and(count_nodes(render('<x-codeart::button href="/x">Go</x-codeart::button>'), '//a[@href="/x"]'))->toBe(1);
});

// ------------------------------------------------------------------------------------------------ fields and forms

it('marks required fields and shows the counter with the old value', function () {
    oldInput(['f' => 'héllo']);
    $xp = render('<x-codeart::field name="f" label="Name" maxlength="20" required />');

    expect(text_of($xp, '//label'))->toBe('Name*')
        ->and(attr($xp, '//label/span', 'aria-hidden'))->toBe('true')
        ->and(attr($xp, '//input', 'required'))->not->toBeNull()
        ->and(attr($xp, '//input', 'value'))->toBe('héllo')
        ->and(attr($xp, '//*[@data-counter]', 'data-counter'))->toBe('20')
        ->and(text_of($xp, '//*[@data-counter]'))->toBe('5 / 20')
        ->and(attr($xp, '//*[@data-field]', 'x-on:input'))->toContain('.length');

    expect(count_nodes(render('<x-codeart::field name="f" label="Name" />'), '//*[@data-counter]'))->toBe(0);
});

it('shows an error the same way for every control', function (string $tag, string $control) {
    $xp = render('<x-codeart::'.$tag.' name="f" label="L" hint="H" :bag="$bag" '.($tag === 'select' ? ':options="[1 => 2]"' : '').' />', ['bag' => bag()]);

    expect(attr($xp, $control, 'aria-invalid'))->toBe('true')
        ->and(attr($xp, $control, 'aria-describedby'))->toBe('field-f-hint field-f-error')
        ->and(text_of($xp, '//*[@id="field-f-error"]'))->toBe('That is wrong')
        ->and(attr($xp, $control, 'class'))->toContain('aria-invalid:border-danger')
        ->and(attr($xp, '//label', 'for'))->toBe('field-f');
})->with([['field', '//input'], ['textarea', '//textarea'], ['select', '//select']]);

it('takes the error from the shared bag by default and ignores other fields', function () {
    view()->share('errors', (new ViewErrorBag)->put('default', bag('x')));

    expect(count_nodes(render('<x-codeart::field name="x" label="L" />'), '//input[@aria-invalid="true"]'))->toBe(1)
        ->and(count_nodes(render('<x-codeart::field name="other" label="L" />'), '//input[@aria-invalid]'))->toBe(0);
});

it('renders prefix and suffix icons and the disabled and readonly states', function () {
    $xp = render('<x-codeart::field name="f" label="L" icon="search" icon-end="mail" disabled readonly />');

    expect(attr($xp, '//input', 'class'))->toContain('ps-12')->toContain('pe-12')
        ->and(attr($xp, '//input', 'disabled'))->not->toBeNull()
        ->and(attr($xp, '//input', 'readonly'))->not->toBeNull()
        ->and(count_nodes($xp, '//div[@class="relative"]/span/svg'))->toBe(2);
});

it('renders a textarea with old value, rows and counter', function () {
    oldInput(['bio' => 'abc']);
    $xp = render('<x-codeart::textarea name="bio" label="Bio" rows="6" maxlength="50" />');

    expect(text_of($xp, '//textarea'))->toBe('abc')
        ->and(attr($xp, '//textarea', 'rows'))->toBe('6')
        ->and(text_of($xp, '//*[@data-counter]'))->toBe('3 / 50');
});

it('renders a select from options with the old value selected', function () {
    oldInput(['plan' => 'pro']);
    $xp = render('<x-codeart::select name="plan" label="Plan" placeholder="Pick" :options="[\'free\' => \'Free\', \'pro\' => \'Pro\']" required />');

    expect(count_nodes($xp, '//select/option'))->toBe(3)
        ->and(attr($xp, '//option[@value="pro"]', 'selected'))->not->toBeNull()
        ->and(attr($xp, '//option[@value="free"]', 'selected'))->toBeNull()
        ->and(attr($xp, '//select', 'required'))->not->toBeNull()
        ->and(attr($xp, '//select', 'class'))->toContain('appearance-none');
});

it('renders checkbox, radio and switch with old state, error and the right role', function () {
    oldInput(['agree' => '1', 'color' => 'b']);

    $checkbox = render('<x-codeart::checkbox id="a" name="agree" label="Agree" :bag="$bag" />', ['bag' => bag('agree')]);
    expect(attr($checkbox, '//input', 'checked'))->not->toBeNull()
        ->and(attr($checkbox, '//input', 'aria-invalid'))->toBe('true')
        ->and(text_of($checkbox, '//*[@id="a-error"]'))->toBe('That is wrong');

    $radioB = render('<x-codeart::radio id="r" name="color" value="b" label="B" />');
    $radioA = render('<x-codeart::radio id="r" name="color" value="a" label="A" :checked="true" />');
    expect(attr($radioB, '//input', 'checked'))->not->toBeNull()
        ->and(attr($radioA, '//input', 'checked'))->toBeNull()
        ->and(attr($radioB, '//input', 'type'))->toBe('radio');

    $switch = render('<x-codeart::switch id="s" name="on" label="On" hint="Help" />');
    expect(attr($switch, '//input', 'role'))->toBe('switch')
        ->and(attr($switch, '//input', 'type'))->toBe('checkbox')
        ->and(attr($switch, '//input', 'aria-describedby'))->toBe('s-hint')
        ->and(attr($switch, '//input', 'checked'))->toBeNull()
        ->and(unlabelled_controls($switch))->toBe([]);
});

// ---------------------------------------------------------------------------------------------- badge, alert, empty

it('renders badges for every tone with a text label', function (string $tone, string $class) {
    $xp = render('<x-codeart::badge tone="'.$tone.'">Label</x-codeart::badge>');

    expect(attr($xp, '//span', 'class'))->toContain($class)
        ->and(attr($xp, '//span', 'data-tone'))->toBe($tone)
        ->and(text_of($xp, '//span'))->toBe('Label');
})->with([['primary', 'bg-primary-soft'], ['success', 'bg-success-bg'], ['warning', 'bg-warning-bg'], ['danger', 'bg-danger-bg'], ['neutral', 'bg-neutral-bg']]);

it('makes an alert dismissible with a button that needs JS', function () {
    $xp = render('<x-codeart::alert type="info" :dismissible="true">Hi</x-codeart::alert>');
    $plain = render('<x-codeart::alert type="info">Hi</x-codeart::alert>');

    expect(attr($xp, '//button', 'aria-label'))->toBe(__('codeart::ui.c.dismiss'))
        ->and(attr($xp, '//button', 'hidden'))->not->toBeNull()
        ->and(count_nodes($plain, '//button'))->toBe(0);
});

it('renders an empty state', function () {
    $xp = render('<x-codeart::empty-state title="Nothing" text="Add one."><a href="/x">Add</a></x-codeart::empty-state>');

    expect(text_of($xp, '//h3'))->toBe('Nothing')
        ->and(count_nodes($xp, '//a[@href="/x"]'))->toBe(1);
});

// ------------------------------------------------------------------------------------------------ modal and confirm

it('renders a modal as a native dialog with title, body and footer', function () {
    $xp = render("<x-codeart::modal id=\"m1\">\n<x-slot:title>My title</x-slot:title>\nBody\n<x-slot:footer><button>OK</button></x-slot:footer>\n</x-codeart::modal>");

    expect(attr($xp, '//dialog', 'id'))->toBe('m1')
        ->and(attr($xp, '//dialog', 'aria-labelledby'))->toBe('m1-title')
        ->and(text_of($xp, '//h2[@id="m1-title"]'))->toBe('My title')
        ->and(attr($xp, '//dialog', 'data-modal'))->not->toBeNull()
        ->and(text_of($xp, '//dialog'))->toContain('Body')
        ->and(count_nodes($xp, '//dialog//button[@data-modal-close][@aria-label]'))->toBe(1)
        ->and(text_of($xp, '//dialog'))->toContain('OK')
        ->and(attr($xp, '//dialog', 'class'))->toContain('backdrop:bg-scrim');
});

it('wraps a destructive form in a confirm dialog with a danger confirm button and a server-side confirmation step', function () {
    $xp = render('<x-codeart::confirm action="/things/1" method="DELETE" title="Delete it?" text="Gone forever." confirm="Yes">Delete</x-codeart::confirm>');

    expect(attr($xp, '//form[@data-confirm-step]', 'method'))->toBe('POST')
        ->and(attr($xp, '//form[@data-confirm-step]', 'action'))->toBe(route('codeart.confirm'))
        ->and(attr($xp, '//form[@data-confirm-step]//input[@name="target"]', 'value'))->toBe('/things/1')
        ->and(count_nodes($xp, '//form[@data-confirm-step]//input[@name="sig"]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-confirm-step]/button[@data-confirm-trigger][@aria-haspopup="dialog"]'))->toBe(1)
        ->and(attr($xp, '//form[@data-confirm-form]', 'action'))->toBe('/things/1')
        ->and(attr($xp, '//form[@data-confirm-form]//input[@name="_method"]', 'value'))->toBe('DELETE')
        ->and(count_nodes($xp, '//form[@data-confirm-form]//input[@name="_token"]'))->toBe(1)
        ->and(attr($xp, '//form/dialog', 'role'))->toBe('alertdialog')
        ->and(text_of($xp, '//form/dialog//h2'))->toBe('Delete it?')
        ->and(text_of($xp, '//form/dialog//p'))->toBe('Gone forever.')
        ->and(attr($xp, '//form/dialog//button[@type="submit"]', 'class'))->toContain('bg-danger')
        ->and(text_of($xp, '//form/dialog//button[@type="submit"]'))->toBe('Yes')
        ->and(count_nodes($xp, '//form/dialog//button[@type="button"][@data-modal-close]'))->toBeGreaterThanOrEqual(1);
});

// ------------------------------------------------------------------------------------------------------------ toast

it('renders session flash messages as toasts in an aria-live region', function () {
    session()->flash('status', 'Saved');
    session()->flash('error', 'Broke');
    session()->flash('toast', ['type' => 'warning', 'message' => 'Careful']);
    $html = Blade::render('<x-codeart::toasts />');
    $xp = dom($html);

    expect(attr($xp, '//*[@data-toasts]', 'aria-live'))->toBe('polite')
        ->and(attr($xp, '//*[@data-toasts]', 'role'))->toBe('region')
        ->and(attr($xp, '//*[@data-toasts]', 'x-on:codeart:toast.window'))->toContain('add(')
        ->and(str_replace('\\u0022', '"', html_entity_decode($html)))->toContain('"message":"Saved"')->toContain('"type":"danger"')->toContain('"message":"Careful"')
        ->and(count_nodes($xp, '//noscript//*[@role="alert"]'))->toBe(1);
});

it('renders an empty toast stack without flashes and ships the toast event in the bundle', function () {
    $xp = render('<x-codeart::toasts />');

    expect(count_nodes($xp, '//noscript'))->toBe(0)
        ->and(file_get_contents(__DIR__.'/../../resources/dist/codeart.js'))->toContain('codeartToasts')
        ->and(file_get_contents(__DIR__.'/../../resources/js/app.js'))->toContain('codeart:toast');
});

it('shows flash messages on admin pages as toasts', function () {
    $this->actingAs(staff('admin'), 'admin')->withSession(['status' => 'Hello there'])->get('/admin')
        ->assertOk()->assertSee('Hello there', false)->assertSee('data-toasts', false);
});

// ---------------------------------------------------------------------------------------------------------- dropdown

it('renders the dropdown with aria-expanded button and a panel', function () {
    $xp = render('<x-codeart::dropdown label="Menu" id="dd"><x-slot:trigger>Open</x-slot:trigger><a data-menu-item href="/a">A</a></x-codeart::dropdown>');

    expect(attr($xp, '//button', 'aria-haspopup'))->toBe('true')
        ->and(attr($xp, '//button', 'aria-controls'))->toBe('dd')
        ->and(attr($xp, '//button', 'aria-expanded'))->toBe('false')
        ->and(attr($xp, '//button', 'aria-label'))->toBe('Menu')
        ->and(attr($xp, '//*[@id="dd"]', 'x-show'))->toBe('open')
        ->and(count_nodes($xp, '//*[@id="dd"]//a[@data-menu-item]'))->toBe(1);
});

it('uses the one dropdown component for the user menu (no second implementation)', function () {
    $menu = file_get_contents(__DIR__.'/../../resources/views/components/admin/user-menu.blade.php');
    $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

    expect($menu)->toContain('<x-codeart::dropdown')->not->toContain('x-on:keydown')->not->toContain('aria-haspopup')
        ->and(substr_count($js, "Alpine.data('codeartDropdown'"))->toBe(1)
        ->and($js)->not->toContain('codeartUserMenu');

    $this->actingAs(staff('editor'), 'admin')->get('/admin')->assertOk()
        ->assertSee('x-data="codeartDropdown"', false)->assertSee('id="user-menu-panel"', false);
});

// ------------------------------------------------------------------------------------------------------- showcase

it('hides the showcase outside the local environment, even from a super-admin', function () {
    $this->app['env'] = 'production';

    $this->actingAs(staff('super-admin'), 'admin')->get('/admin/_ui')->assertNotFound();
    $this->get('/admin/_ui')->assertNotFound(); // guests learn nothing either
});

it('answers 403 to staff that are not super-admin and sends guests to sign in, in local', function () {
    $this->app['env'] = 'local';

    $this->actingAs(staff('admin'), 'admin')->get('/admin/_ui')->assertForbidden();
    auth('admin')->logout();
    $this->get('/admin/_ui')->assertRedirect(route('login'));
});

it('shows every component to a super-admin in local, with no menu item for it', function () {
    $this->app['env'] = 'local';
    $response = $this->actingAs(staff('super-admin'), 'admin')->get('/admin/_ui')->assertOk();
    $xp = dom($response->getContent());

    expect(route('codeart.ui-showcase'))->toEndWith('/admin/_ui')
        ->and(count_nodes($xp, '//dialog'))->toBeGreaterThanOrEqual(2)
        ->and(count_nodes($xp, '//input[@role="switch"]'))->toBe(3)
        ->and(count_nodes($xp, '//*[@data-counter]'))->toBeGreaterThanOrEqual(3)
        ->and(count_nodes($xp, '//button[@aria-busy="true"]'))->toBe(1)
        ->and(count_nodes($xp, '//*[@data-tone][not(ancestor::*[@data-table])]'))->toBe(6)
        ->and(count_nodes($xp, '//aside//a[contains(@href,"_ui")]'))->toBe(0)
        ->and(unlabelled_controls($xp))->toBe([]);
});

it('validates the showcase form and flashes a toast on success', function () {
    $this->app['env'] = 'local';
    $user = staff('super-admin');

    $this->withSession(['_token' => 't'])->actingAs($user, 'admin')->from('/admin/_ui')->post('/admin/_ui', ['_token' => 't', 'name' => str_repeat('x', 41)])
        ->assertRedirect('/admin/_ui')->assertSessionHasErrors(['name', 'plan', 'terms']);

    $this->withSession(['_token' => 't'])->actingAs($user, 'admin')->post('/admin/_ui', ['_token' => 't', 'name' => 'Ana', 'plan' => 'pro', 'terms' => '1'])
        ->assertRedirect('/admin/_ui')->assertSessionHas('toast.type', 'success');
});
