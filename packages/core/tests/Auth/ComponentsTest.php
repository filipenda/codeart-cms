<?php

use Codeart\Core\Settings\Settings;
use Codeart\Core\Theme\Assets;
use Codeart\Core\Theme\Color;
use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;

require_once __DIR__.'/../Support/dom.php';

const DIST = __DIR__.'/../../resources/dist';

/** @return list<string> */
function localeButtons(string $html): array
{
    return array_map(fn (DOMNode $n) => $n->getAttribute('value'), iterator_to_array(dom($html)->query('//details//button[@name="locale"]')));
}

// -------------------------------------------------------------------------------------------------- language switcher

it('lists only the enabled locales, with their own names, and no flags', function () {
    $xp = dom($this->get('/admin/login')->getContent());

    expect(localeButtons($this->get('/admin/login')->getContent()))->toBe(['sr', 'sr-Cyrl', 'en'])
        ->and(array_map(fn (DOMNode $n) => trim($n->textContent), iterator_to_array($xp->query('//details//button/span[1]'))))->toBe(['Srpski', 'Српски', 'English'])
        ->and(attr($xp, '//details//form', 'method'))->toBe('POST')
        ->and(attr($xp, '//details//form', 'action'))->toBe(route('codeart.locale'))
        ->and(count_nodes($xp, '//details//img'))->toBe(0);

    app(Settings::class)->set('locales.enabled', ['sr', 'sr-Cyrl']);
    expect(localeButtons($this->get('/admin/login')->getContent()))->toBe(['sr', 'sr-Cyrl']);
});

it('hides the language dropdown when only one locale is enabled', function () {
    app(Settings::class)->set('locales.enabled', ['sr']);

    expect(count_nodes(dom($this->get('/admin/login')->getContent()), '//details'))->toBe(0);
});

it('validates the chosen locale against the enabled ones', function () {
    app(Settings::class)->set('locales.enabled', ['sr', 'sr-Cyrl']);

    $this->from('/admin/login')->post('/admin/locale', ['locale' => 'en'])->assertRedirect('/admin/login')->assertSessionHasErrors('locale');
    $this->from('/admin/login')->post('/admin/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    $this->from('/admin/login')->post('/admin/locale', [])->assertSessionHasErrors('locale');
    $this->postJson('/admin/locale', ['locale' => 'en'])->assertStatus(422);
    expect(session('codeart.locale'))->toBeNull();
});

it('remembers the choice in the session and a cookie, and changes the screen language', function () {
    $this->from('/admin/login')->post('/admin/locale', ['locale' => 'en'])
        ->assertRedirect('/admin/login')
        ->assertSessionHas('codeart.locale', 'en')
        ->assertCookie('codeart_locale', 'en');

    $page = $this->get('/admin/login')->getContent();
    expect($page)->toContain('<html lang="en"')->toContain('Sign in')->not->toContain('Prijavi se');
});

it('falls back to the cookie, then to the default language', function () {
    $this->disableCookieEncryption()->withoutMiddleware(EncryptCookies::class)->withCookie('codeart_locale', 'en');
    expect($this->get('/admin/login')->getContent())->toContain('<html lang="en"');

    $this->flushSession();
    expect($this->withCookie('codeart_locale', 'xx')->get('/admin/login')->getContent())->toContain('<html lang="sr"');
});

it('saves the choice on the signed-in user', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->from('/admin/login')->post('/admin/locale', ['locale' => 'sr-Cyrl'])->assertRedirect('/admin/login');

    expect($user->fresh()->locale)->toBe('sr-Cyrl');
});

it('uses the saved language of a signed-in user when they have not chosen one', function () {
    $this->actingAs(staff('editor', ['locale' => 'en', 'email_verified_at' => null]), 'admin');

    expect($this->get('/admin/email/verify')->getContent())->toContain('<html lang="en"')->toContain('Verify your e-mail');
});

it('renders the screens in sr, sr-Cyrl and en', function (string $locale, string $title, string $htmlLang) {
    $response = $this->withSession(['codeart.locale' => $locale])->get('/admin/login')->assertOk();

    expect($response->getContent())->toContain('<html lang="'.$htmlLang.'"')->toContain('>'.$title.'<');
})->with([
    'sr' => ['sr', 'Dobro došli', 'sr'],
    'sr-Cyrl' => ['sr-Cyrl', 'Добро дошли', 'sr-Cyrl'],
    'en' => ['en', 'Welcome', 'en'],
]);

// ------------------------------------------------------------------------------------------------------ logo / theme

it('shows the text wordmark when no logo is set', function () {
    $xp = dom($this->get('/admin/login')->getContent());

    expect(count_nodes($xp, '//main//img'))->toBe(0)
        ->and(text_of($xp, '//main//span[contains(@class,"tracking-")]'))->toBe('CODEART');
});

it('renders both logos and lets the theme decide, and one logo for both when only one is set', function () {
    $settings = app(Settings::class);
    $settings->set('theme.logo_light', 'branding/light.png');
    $settings->set('theme.logo_dark', 'branding/dark.png');
    $xp = dom($this->get('/admin/login')->getContent());

    expect(attr($xp, '//img[contains(@class,"codeart-logo-light")]', 'src'))->toEndWith('branding/light.png')
        ->and(attr($xp, '//img[contains(@class,"codeart-logo-dark")]', 'src'))->toEndWith('branding/dark.png')
        ->and(attr($xp, '//img[contains(@class,"codeart-logo-light")]', 'alt'))->not->toBe('')
        ->and(attr($xp, '//img[contains(@class,"codeart-logo-dark")]', 'alt'))->toBe('') // not announced twice
        ->and(attr($xp, '//img[contains(@class,"codeart-logo-light")]', 'class'))->toContain('h-12');

    $settings->forget('theme.logo_dark');
    $xp = dom($this->get('/admin/login')->getContent());
    expect(count_nodes($xp, '//main//img'))->toBe(1)
        ->and(attr($xp, '//main//img', 'class'))->not->toContain('codeart-logo');
});

it('toggles the logos by data-theme in the built CSS, also for auto', function () {
    $css = (string) file_get_contents(DIST.'/codeart.css');

    expect($css)->toContain('.codeart-logo-dark,[data-theme=dark] .codeart-logo-light{display:none}')
        ->toContain('[data-theme=dark] .codeart-logo-dark{display:inline-block}')
        ->toContain('prefers-color-scheme:dark')
        ->toContain(':root[data-theme=auto] .codeart-logo-light{display:none}');
});

it('puts the theme mode on the html tag', function (string $mode) {
    app(Settings::class)->set('theme.mode', $mode);

    expect($this->get('/admin/login')->getContent())->toContain('data-theme="'.$mode.'"');
})->with(['light', 'dark', 'auto']);

it('has the new tokens for light, dark and auto', function () {
    $tokens = (string) file_get_contents(__DIR__.'/../../resources/css/tokens.css');
    [$light, $dark] = explode('[data-theme="dark"]', $tokens, 2);
    [$darkOnly, $auto] = explode('prefers-color-scheme: dark', $dark, 2);

    foreach (['--shadow-card', '--color-primary-soft'] as $token) {
        expect($light)->toContain($token.':')->and($darkOnly)->toContain($token.':')->and($auto)->toContain($token.':');
    }
    expect($light)->toContain('--radius-card: 1.5rem')->toContain('--radius-control: 0.875rem')->toContain('color-mix(in srgb, var(--color-primary)');
});

it('reads the primary button contrast above 4.5 for the default and for very light and very dark primaries', function (?string $primary) {
    $theme = app(ThemeSettings::class);
    $settings = app(Settings::class);
    if ($primary !== null) {
        $settings->set('theme.primary', $primary);
    }
    $overrides = $theme->overrides()['light'];

    // the default comes straight from tokens.css; a custom color gets its text color from ThemeSettings
    $tokens = (string) file_get_contents(__DIR__.'/../../resources/css/tokens.css');
    preg_match('/--color-primary: (#[0-9a-f]{6})/', $tokens, $bg);
    preg_match('/--color-on-primary: (#[0-9a-f]{6})/', $tokens, $fg);
    $background = $overrides['--color-primary'] ?? $bg[1];
    $text = $overrides['--color-on-primary'] ?? $fg[1];

    expect(Color::contrast($background, $text))->toBeGreaterThan(4.5);
    // and the hover shade stays readable too
    expect(Color::contrast($overrides['--color-primary-hover'] ?? '#b6dd14', $text))->toBeGreaterThan(4.5);
})->with(['default' => [null], 'very light' => ['#FFFFE0'], 'very dark' => ['#0A0A23'], 'mid blue' => ['#2563EB']]);

// ----------------------------------------------------------------------------------------------------------- assets

it('ships the built CSS and JS with the component classes and Alpine components', function () {
    expect(DIST.'/codeart.css')->toBeFile()->and(DIST.'/codeart.js')->toBeFile();
    $css = (string) file_get_contents(DIST.'/codeart.css');
    $js = (string) file_get_contents(DIST.'/codeart.js');

    foreach (['.rounded-card', '.rounded-control', '.h-13', '.max-w-120', '.shadow-card', '.fill-primary-soft', '.bg-primary', '.text-on-primary', '.border-primary', '.text-link'] as $class) {
        expect($css)->toContain($class);
    }
    expect($css)->toContain('aria-selected')->toContain('aria-invalid')->toContain('--color-primary-soft');
    expect($js)->toContain('codeartOtp')->toContain('codeartRecoveryCodes')->toContain('data-otp-box');
});

it('prints versioned asset tags from a content hash, not the time', function () {
    $html = Blade::render('<x-codeart::assets />');
    $css = substr(hash_file('xxh128', DIST.'/codeart.css'), 0, 12);
    $js = substr(hash_file('xxh128', DIST.'/codeart.js'), 0, 12);

    expect($html)->toContain('<link rel="stylesheet" href="http://localhost/vendor/codeart/codeart.css?v='.$css.'">')
        ->toContain('<script src="http://localhost/vendor/codeart/codeart.js?v='.$js.'" defer></script>')
        ->and(app(Assets::class)->version('codeart.css'))->toBe($css)
        ->and(app(Assets::class)->version('missing.js'))->toBe('0');
});

it('publishes both files with the codeart-assets tag', function () {
    $target = public_path('vendor/codeart');
    @unlink($target.'/codeart.css');
    @unlink($target.'/codeart.js');

    $this->artisan('vendor:publish', ['--tag' => 'codeart-assets', '--force' => true])->assertSuccessful();
    expect($target.'/codeart.css')->toBeFile()->and($target.'/codeart.js')->toBeFile();
    @unlink($target.'/codeart.css');
    @unlink($target.'/codeart.js');
});

it('publishes the views so an app can override them', function () {
    $this->artisan('vendor:publish', ['--tag' => 'codeart-views', '--force' => true])->assertSuccessful();
    $login = resource_path('views/vendor/codeart/auth/login.blade.php');
    expect($login)->toBeFile();

    // the published copy wins over the package's
    file_put_contents($login, 'OVERRIDDEN');
    expect($this->get('/admin/login')->getContent())->toBe('OVERRIDDEN');
    exec('rm -rf '.escapeshellarg(resource_path('views/vendor/codeart')));
});

// ------------------------------------------------------------------------------------------------------ components

it('wires label, hint and error to the control', function () {
    $html = Blade::render('<x-codeart::field name="email" id="e1" label="E-mail" hint="We never share it" :bag="$bag" icon="mail" />', ['bag' => new MessageBag(['email' => ['Bad address']])]);
    $xp = dom($html);

    expect(attr($xp, '//label', 'for'))->toBe('e1')
        ->and(attr($xp, '//input', 'id'))->toBe('e1')
        ->and(attr($xp, '//input', 'aria-invalid'))->toBe('true')
        ->and(attr($xp, '//input', 'aria-describedby'))->toBe('e1-hint e1-error')
        ->and(text_of($xp, '//*[@id="e1-hint"]'))->toBe('We never share it')
        ->and(text_of($xp, '//*[@id="e1-error"]'))->toBe('Bad address');

    $clean = dom(Blade::render('<x-codeart::field name="email" id="e1" label="E-mail" :bag="$bag" />', ['bag' => new MessageBag]));
    expect(attr($clean, '//input', 'aria-invalid'))->toBeNull()->and(attr($clean, '//input', 'aria-describedby'))->toBeNull();
});

it('uses role=alert for errors and a polite status for the rest, with an icon so color is not the only signal', function () {
    $error = dom(Blade::render('<x-codeart::alert type="danger" title="Oops">Text</x-codeart::alert>'));
    $info = dom(Blade::render('<x-codeart::alert type="success">Done</x-codeart::alert>'));

    expect(attr($error, '//div', 'role'))->toBe('alert')
        ->and(count_nodes($error, '//div//svg'))->toBe(1)
        ->and(attr($info, '//div', 'role'))->toBe('status')
        ->and(attr($info, '//div', 'aria-live'))->toBe('polite');
});

it('keeps a single submitted value for the one-time code (six boxes are built by JS only)', function () {
    $xp = dom(Blade::render('<x-codeart::otp-input id="otp" name="code" />'));

    expect(count_nodes($xp, '//input[@name="code"]'))->toBe(1)
        ->and(attr($xp, '//input[@name="code"]', 'inputmode'))->toBe('numeric')
        ->and(attr($xp, '//input[@name="code"]', 'autocomplete'))->toBe('one-time-code')
        ->and(attr($xp, '//input[@name="code"]', 'maxlength'))->toBe('6')
        ->and(count_nodes($xp, '//input[@data-otp-box][not(ancestor::template)]'))->toBe(0) // the boxes exist only inside a <template x-for>
        ->and(count_nodes($xp, '//template'))->toBe(1);
});

it('renders a button as a plain submit that Alpine can lock', function () {
    $xp = dom(Blade::render('<x-codeart::button icon="arrow-right">Go</x-codeart::button>'));

    expect(attr($xp, '//button', 'type'))->toBe('submit')
        ->and(attr($xp, '//button', 'class'))->toContain('h-13')->toContain('rounded-control')->toContain('bg-primary')->toContain('text-on-primary')
        ->and(attr($xp, '//button', 'x-on:click'))->toContain('if (loading)')
        ->and(count_nodes($xp, '//button//svg[@hidden]'))->toBe(1); // the spinner exists but is hidden
});

it('has the small icon set inline, with no external requests', function () {
    foreach (['mail', 'lock', 'eye', 'eye-off', 'arrow-right', 'check', 'alert', 'globe', 'shield'] as $name) {
        $html = Blade::render('<x-codeart::icon name="'.$name.'" />');
        expect($html)->toContain('<svg')->toContain('aria-hidden="true"')->toMatch('/<(path|circle|rect)/')->not->toContain('href=')->not->toContain('src=');
    }
});

it('finds no color literal in any view, script or stylesheet of the package', function () {
    require_once __DIR__.'/../../../../tools/check-colors.php';
    $violations = codeart_find_color_violations(dirname(__DIR__, 2), ['config/codeart.php', 'resources/css/tokens.css']);

    expect($violations)->toBe([]);
});

// ------------------------------------------------------------------------------------------------- brand command

function pngFile(string $path): void
{
    $image = imagecreatetruecolor(8, 8);
    imagepng($image, $path);
}

it('loads the logos and favicon through the image upload service', function () {
    Storage::fake('public');
    $dir = sys_get_temp_dir().'/codeart-brand-'.uniqid();
    mkdir($dir);
    foreach (['logo-light-bg.png', 'logo-dark-bg.png', 'favicon.png'] as $file) {
        pngFile($dir.'/'.$file);
    }

    $this->artisan('codeart:brand:load', ['path' => $dir])->assertSuccessful();

    $settings = app(Settings::class);
    foreach (['theme.logo_light', 'theme.logo_dark', 'theme.favicon'] as $key) {
        expect($settings->get($key))->toStartWith('branding/');
        Storage::disk('public')->assertExists($settings->get($key));
    }

    // and the sign-in page now shows them
    $xp = dom($this->get('/admin/login')->getContent());
    expect(count_nodes($xp, '//main//img'))->toBe(2)->and(attr($xp, '//link[@rel="icon"]', 'href'))->toContain('branding/');
    exec('rm -rf '.escapeshellarg($dir));
});

it('skips what is missing or not an image, and fails when nothing could be loaded', function () {
    Storage::fake('public');
    $dir = sys_get_temp_dir().'/codeart-brand-'.uniqid();
    mkdir($dir);
    pngFile($dir.'/favicon.png');
    file_put_contents($dir.'/logo-light-bg.png', 'this is not an image');

    $this->artisan('codeart:brand:load', ['path' => $dir])->assertSuccessful();
    expect(app(Settings::class)->get('theme.favicon'))->toStartWith('branding/')->and(app(Settings::class)->get('theme.logo_light'))->toBeNull();

    $this->artisan('codeart:brand:load', ['path' => $dir.'/nope'])->assertFailed();
    exec('rm -rf '.escapeshellarg($dir));
});
