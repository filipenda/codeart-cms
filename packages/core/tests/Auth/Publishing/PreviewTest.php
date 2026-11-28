<?php

require_once __DIR__.'/../../Support/publishing.php';

use Codeart\Core\Publishing\Actions\SaveDraft;
use Codeart\Core\Publishing\Preview;
use Codeart\Core\Publishing\PreviewToken;
use Codeart\Core\Publishing\PreviewTokens;
use Codeart\Core\Tests\Support\PubItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    makePubTables();
    config()->set('pubitems.public_url', '/site/{slug}');
    // What a client site does: the previewed record when the link is valid, else the published one; then the content.
    Route::middleware('web')->get('/site/{slug}', function (Request $request, string $slug) {
        $item = Preview::model($request, PubItem::class) ?? PubItem::previewable($request)->whereTranslatedSlug($slug, 'sr')->firstOrFail();
        $content = Preview::resolve($request, $item, 'sr');

        return $content->title.($content->isPreview ? '|draft' : '|live');
    });
});

/** A published item whose draft says "Nacrt" and a preview link for $user. */
function previewLink(?PubItem $item = null, $user = null, ?string $locale = 'sr'): array
{
    $item ??= pubItem(['title' => 'Objavljena']);
    $user ??= staff('editor');
    $draft = app(SaveDraft::class)($item, ['translations' => ['sr' => ['title' => 'Nacrt']]], $user);

    return [$item, $user, Preview::url($item->fresh(), $draft, $user, $locale)];
}

it('shows the draft to the editor the link was issued for', function () {
    [$item, $user, $url] = previewLink();

    $this->actingAs($user, 'admin')->get($url)->assertOk()->assertSee('Nacrt|draft');
    expect($url)->toContain('/site/objavljena?codeart_preview=');
});

it('sends noindex and no-store headers on a preview, valid or not, and not on the normal page', function () {
    [$item, $user, $url] = previewLink();

    $this->actingAs($user, 'admin')->get($url)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->get('/site/objavljena?codeart_preview=garbage')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/site/objavljena')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertSee('Objavljena|live');
});

it('gives the published text to guests, even with a valid-looking link', function () {
    [, , $url] = previewLink();

    $this->get($url)->assertOk()->assertSee('Objavljena|live');
});

it('refuses a tampered or unknown token', function () {
    [, $user, $url] = previewLink();
    $broken = substr($url, 0, -1).(str_ends_with($url, 'a') ? 'b' : 'a');

    $this->actingAs($user, 'admin')->get($broken)->assertOk()->assertSee('Objavljena|live');
});

it('refuses an expired link', function () {
    [, $user, $url] = previewLink();
    $this->travel(61)->minutes();

    $this->actingAs($user, 'admin')->get($url)->assertOk()->assertSee('Objavljena|live');
});

it('honours the configured lifetime', function () {
    config()->set('codeart.preview.ttl_minutes', 5);
    [, $user, $url] = previewLink();
    $this->travel(4)->minutes();
    $this->actingAs($user, 'admin')->get($url)->assertSee('Nacrt|draft');
    $this->travel(2)->minutes();
    $this->actingAs($user, 'admin')->get($url)->assertSee('Objavljena|live');
});

it('refuses a link used by another user', function () {
    [, , $url] = previewLink();

    $this->actingAs(staff('editor'), 'admin')->get($url)->assertOk()->assertSee('Objavljena|live');
});

it('refuses a user who has lost the permission', function () {
    [, $user, $url] = previewLink();
    Role::findByName('editor', 'admin')->revokePermissionTo('pubitems.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh(), 'admin')->get($url)->assertOk()->assertSee('Objavljena|live');
});

it('binds a link to its record: another record never shows a draft', function () {
    [, $user, $url] = previewLink();
    $other = pubItem(['title' => 'Druga']);
    app(SaveDraft::class)($other, ['translations' => ['sr' => ['title' => 'Tajni nacrt']]]);
    $token = Preview::class::parameter();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $this->actingAs($user, 'admin')->get('/site/druga?'.$token.'='.$query[$token])->assertOk()->assertSee('Nacrt|draft'); // the linked record, not the slug's
    expect(Preview::resolve(Request::create('/'), $other, 'sr')->title)->toBe('Druga');
});

it('stores only a hash of the token and removes expired rows', function () {
    [, $user, $url] = previewLink();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $token = $query['codeart_preview'];

    expect(PreviewToken::query()->where('token_hash', $token)->exists())->toBeFalse()
        ->and(PreviewToken::query()->where('token_hash', hash('sha256', $token))->exists())->toBeTrue()
        ->and(strlen($token))->toBe(48);

    $this->travel(2)->hours();
    expect(app(PreviewTokens::class)->prune())->toBe(1)->and(PreviewToken::query()->count())->toBe(0);
});

it('uses the draft for the Cyrillic locale by transliterating the Serbian draft', function () {
    [$item, $user] = previewLink();
    $draft = app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => 'Ljubav']]], $user);
    $token = app(PreviewTokens::class)->issue($item->fresh(), $draft, $user);
    $request = Request::create('/x?codeart_preview='.$token);
    $request->setUserResolver(fn () => $user);

    expect(Preview::resolve($request, $item->fresh(), 'sr-Cyrl')->title)->toBe('Љубав');
});

it('shows the frame page only to the token owner, with three width buttons and the real page inside', function () {
    [, $user, $url] = previewLink();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $path = '/admin/preview/'.$query['codeart_preview'];

    $html = $this->actingAs($user, 'admin')->get($path)->assertOk()->getContent();
    expect(substr_count($html, 'data-device='))->toBe(3)
        ->and($html)->toContain('data-width="375px"')->toContain('data-width="768px"')->toContain('data-width="100%"')
        ->and($html)->toContain('data-preview-iframe')->toContain(e($url));

    $this->actingAs(staff('editor'), 'admin')->get($path)->assertNotFound();
    auth('admin')->logout();
    $this->get($path)->assertRedirect();
});
