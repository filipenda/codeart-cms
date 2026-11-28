<?php

require_once __DIR__.'/../../Support/media.php';

use Codeart\Core\Media\Actions\StoreMedia;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => fake_disk('public'));

it('renders the library page with grid, list, folder tree, drawer and the effective limit', function () {
    $admin = mediaAdmin();
    $html = $this->actingAs($admin, 'admin')->get(route('admin.media.index'))->assertOk()->getContent();
    expect($html)->toContain('data-media-browser="page"')->toContain('data-media-grid')->toContain('data-media-list')->toContain('data-folder-tree')
        ->toContain('data-media-drawer')->toContain('data-upload-limit')->toContain('data-bulk-delete')->toContain('data-media-settings')
        ->toContain('codeartMedia(')->toMatch('#media\\\\*\/items#')->not->toContain('<script>alert');
});

it('hides what the user may not do', function () {
    $user = mediaAdmin('editor');
    Role::findByName('editor', 'admin')->revokePermissionTo('media.upload');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $html = $this->actingAs($user->fresh(), 'admin')->get(route('admin.media.index'))->assertOk()->getContent();
    expect($html)->not->toContain('data-media-input')->not->toContain('data-bulk-delete')->not->toContain('data-media-settings')->not->toContain('data-media-texts');
});

it('renders the form field with ids, reorder buttons and the picker once', function () {
    $admin = mediaAdmin();
    $a = app(StoreMedia::class)(upload('a.jpg', imageBytes('jpeg', 300, 200)), $admin, null, 'keep');
    $b = app(StoreMedia::class)(upload('b.jpg', imageBytes('jpeg', 301, 200)), $admin, null, 'keep');
    $this->actingAs($admin, 'admin');
    $html = Blade::render('<x-codeart::form.media name="gallery" label="Galerija" :value="$ids" multiple /><x-codeart::form.media name="cover" label="Naslovna" :value="$cover" />',
        ['ids' => [$b->id, $a->id], 'cover' => $a->id]);

    expect($html)->toContain('data-media-field="gallery"')->toContain('name="gallery[]"')->toContain('name="cover"')
        ->toContain('Galerija')->toContain('data-media-choose')
        ->and(substr_count($html, 'data-media-picker'))->toBe(1)
        ->and(preg_match('/codeartMediaField\(.*?\\\\u0022id\\\\u0022:(\d+)/s', $html, $first))->toBe(1)
        ->and((int) $first[1])->toBe($b->id);
});
