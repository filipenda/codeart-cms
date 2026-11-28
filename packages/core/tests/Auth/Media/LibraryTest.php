<?php

require_once __DIR__.'/../../Support/media.php';

use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Media\Actions\DeleteMedia;
use Codeart\Core\Media\Actions\StoreMedia;
use Codeart\Core\Media\HasMedia;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaFolder;
use Codeart\Core\Media\MediaInUse;
use Codeart\Core\Media\MediaUsage;
use Codeart\Core\Media\MediaVariants;
use Codeart\Core\Media\UploadLimit;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    fake_disk('public');
    app(Settings::class)->set('media.max_kb', 10240);
});

/** A tiny model that uses media (the users table stands in for a content table). */
class MediaOwner extends AdminTestUser
{
    use HasMedia;

    public function displayTitle(): string
    {
        return 'Owner '.$this->id;
    }
}

function mediaFile(string $name = 'pic.jpg', string $type = 'jpeg', int $w = 1000, int $h = 600): Media
{
    return app(StoreMedia::class)(upload($name, imageBytes($type, $w, $h)), null, null, 'keep');
}

function mediaToken($test, AdminTestUser $user, string $path): string
{
    $tokens = app(ConfirmationTokens::class);

    return $tokens->issue($user, 'action:POST:'.$path);
}

// ------------------------------------------------------------------------------------------------- variants

it('pre-generates thumb, medium and large plus webp copies, never enlarging', function () {
    $media = mediaFile('big.jpg', 'jpeg', 2400, 1200);
    expect(array_keys($media->variants))->toBe(['thumb', 'medium', 'large'])
        ->and($media->variants['thumb']['width'])->toBe(320)
        ->and($media->variants['medium']['width'])->toBe(800)
        ->and($media->variants['large']['width'])->toBe(1600);
    foreach ($media->variants as $variant) {
        Storage::disk('public')->assertExists($variant['path']);
        Storage::disk('public')->assertExists($variant['webp']);
        expect($variant['webp'])->toEndWith('.webp');
    }
    expect($media->srcset())->toContain('320w')->toContain('2400w')->and($media->variantUrl('thumb'))->toContain('-thumb.jpg');

    $small = mediaFile('small.png', 'png', 200, 100);
    expect(array_keys($small->variants))->toBe(['thumb'])->and($small->variants['thumb']['width'])->toBe(200);
});

it('keeps a usable original when a variant cannot be made', function () {
    $media = mediaFile('ok.jpg');
    Storage::disk('public')->put($media->path, 'not an image any more');
    app(MediaVariants::class)->generate($media->fresh());
    $media = $media->fresh();
    expect($media->variants)->toBeNull()->and($media->variantUrl('thumb'))->toBe($media->url());
});

it('regenerates sizes with media:regenerate', function () {
    $media = mediaFile('r.jpg', 'jpeg', 1700, 900);
    foreach ($media->variants as $v) {
        Storage::disk('public')->delete($v['path']);
    }
    $this->artisan('media:regenerate', ['--id' => [$media->id]])->assertSuccessful();
    foreach ($media->fresh()->variants as $v) {
        Storage::disk('public')->assertExists($v['path']);
    }
    $this->artisan('media:regenerate', ['--missing' => true])->assertSuccessful();
});

// ------------------------------------------------------------------------------------------------- http

it('uploads over HTTP, answers JSON and writes the activity log', function () {
    $admin = mediaAdmin();
    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.media.store'), ['file' => upload('Foto.jpg', imageBytes('jpeg'))]);
    $response->assertCreated()->assertJsonPath('media.name', 'Foto.jpg')->assertJsonStructure(['media' => ['id', 'url', 'thumb', 'srcset', 'width']]);
    expect(Activity::query()->where('event', 'media.uploaded')->count())->toBe(1)
        ->and(Media::query()->first()->uploaded_by)->toBe($admin->id);
});

it('answers 422 with a plain message for a refused file and 409 for a duplicate', function () {
    $admin = mediaAdmin();
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.store'), ['file' => upload('a.jpg', '<?php 1;', 'image/jpeg')])
        ->assertStatus(422)->assertJsonPath('reason', 'type_not_allowed');
    $bytes = imageBytes('png', 30, 30);
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.store'), ['file' => upload('a.png', $bytes)])->assertCreated();
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.store'), ['file' => upload('b.png', $bytes)])
        ->assertStatus(409)->assertJsonPath('reason', 'duplicate')->assertJsonStructure(['duplicate' => ['id']]);
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.store'), ['file' => upload('b.png', $bytes), 'duplicate' => 'reuse'])->assertCreated();
    expect(Media::query()->count())->toBe(1);
});

it('gives each role its permissions: admin may all, editor views and uploads only, member nothing', function () {
    $admin = mediaAdmin('admin');
    $editor = mediaAdmin('editor');
    $media = mediaFile();

    $this->actingAs($admin, 'admin')->getJson(route('admin.media.items'))->assertOk();
    $this->actingAs($admin, 'admin')->get(route('admin.media.index'))->assertOk()->assertSee('data-media-browser', false);

    $this->actingAs($editor, 'admin')->getJson(route('admin.media.items'))->assertOk();
    $this->actingAs($editor, 'admin')->getJson(route('admin.media.show', $media->id))->assertOk();
    $this->actingAs($editor, 'admin')->postJson(route('admin.media.store'), ['file' => upload('x.jpg', imageBytes('jpeg', 321, 123))])->assertSuccessful();
    foreach ([['putJson', route('admin.media.update', $media->id), []], ['postJson', route('admin.media.bulk-move'), ['ids' => [$media->id]]]] as $call) {
        $this->actingAs($editor, 'admin')->{$call[0]}($call[1], $call[2] ?? [])->assertForbidden();
    }

    Role::findByName('editor', 'admin')->revokePermissionTo(['media.view', 'media.upload']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($editor->fresh(), 'admin')->get(route('admin.media.index'))->assertForbidden();
    $this->actingAs($editor->fresh(), 'admin')->postJson(route('admin.media.store'), ['file' => upload('x.jpg', imageBytes())])->assertForbidden();
});

it('needs a signed-in staff user', function () {
    $this->getJson(route('admin.media.items'))->assertUnauthorized();
});

it('shows the media menu item only to people with media.view', function () {
    $this->actingAs(mediaAdmin('admin'), 'admin')->get(route('admin.dashboard'))->assertSee(route('admin.media.index'), false);
    $editor = mediaAdmin('editor');
    $this->actingAs($editor, 'admin')->get(route('admin.dashboard'))->assertSee(route('admin.media.index'), false);
    Role::findByName('editor', 'admin')->revokePermissionTo('media.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($editor->fresh(), 'admin')->get(route('admin.dashboard'))->assertDontSee(route('admin.media.index'), false);
});

it('edits alt text and title per language; sr-Cyrl follows sr', function () {
    $admin = mediaAdmin();
    $media = mediaFile();
    $this->actingAs($admin, 'admin')->putJson(route('admin.media.update', $media->id), ['translations' => [
        'sr' => ['alt' => 'Ljubav i njiva', 'title' => 'Naslov'],
        'en' => ['alt' => 'Love and field', 'title' => 'Title'],
    ]])->assertOk();

    $fresh = $media->fresh();
    expect($fresh->translated('alt', 'sr'))->toBe('Ljubav i njiva')
        ->and($fresh->translated('alt', 'en'))->toBe('Love and field')
        ->and($fresh->translated('alt', 'sr-Cyrl'))->toBe('Љубав и њива');
    $this->actingAs($admin, 'admin')->getJson(route('admin.media.show', $media->id))->assertOk()
        ->assertJsonPath('translations.en.alt', 'Love and field')->assertJsonPath('translations.sr.title', 'Naslov');
});

it('replaces the file keeping id, texts and usages', function () {
    $admin = mediaAdmin();
    $media = mediaFile('one.jpg');
    $media->setTranslation('sr', ['alt' => 'Opis']);
    $owner = MediaOwner::query()->find(staff('editor')->id);
    $owner->syncMedia('cover', [$media->id]);
    $old = $media->path;

    $this->actingAs($admin, 'admin')->postJson(route('admin.media.replace', $media->id), ['file' => upload('two.png', imageBytes('png', 500, 300))])->assertOk();
    $fresh = $media->fresh();
    expect($fresh->id)->toBe($media->id)->and($fresh->path)->not->toBe($old)->and($fresh->mime)->toBe('image/png')
        ->and($fresh->translated('alt', 'sr'))->toBe('Opis')->and(MediaUsage::count($fresh))->toBe(1);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($fresh->path);

    $this->actingAs($admin, 'admin')->postJson(route('admin.media.replace', $media->id), ['file' => upload('doc.pdf', "%PDF-1.4\ntrailer<<>>\n%%EOF")])->assertStatus(422)->assertJsonPath('reason', 'replace_kind');
});

// ------------------------------------------------------------------------------------------------- folders, move

it('manages nested folders and refuses a loop', function () {
    $admin = mediaAdmin();
    $a = $this->actingAs($admin, 'admin')->postJson(route('admin.media.folders.store'), ['name' => 'A'])->assertCreated()->json('id');
    $b = $this->actingAs($admin, 'admin')->postJson(route('admin.media.folders.store'), ['name' => 'B', 'parent_id' => $a])->assertCreated()->json('id');
    expect(MediaFolder::tree())->toMatchArray([['id' => $a, 'parent_id' => null, 'name' => 'A', 'depth' => 0], ['id' => $b, 'parent_id' => $a, 'name' => 'B', 'depth' => 1]]);

    $this->actingAs($admin, 'admin')->putJson(route('admin.media.folders.update', $a), ['parent_id' => $b])->assertStatus(422);
    $this->actingAs($admin, 'admin')->putJson(route('admin.media.folders.update', $b), ['name' => 'B2'])->assertOk();

    $media = mediaFile();
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-move'), ['ids' => [$media->id], 'folder_id' => $b])->assertOk()->assertJsonPath('moved', 1);
    expect($media->fresh()->folder_id)->toBe($b);

    // deleting a folder needs a confirmation token; files and children move up
    $path = parse_url(route('admin.media.folders.destroy'), PHP_URL_PATH);
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.folders.destroy'), ['id' => $b])->assertStatus(428);
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.folders.destroy'), ['id' => $b, 'confirmation' => mediaToken($this, $admin, $path)])->assertOk();
    expect(MediaFolder::query()->count())->toBe(1)->and($media->fresh()->folder_id)->toBe($a);
});

// ------------------------------------------------------------------------------------------------- delete + usage

it('bulk deletes only with a server-issued confirmation token, once', function () {
    $admin = mediaAdmin();
    $a = mediaFile('a.jpg');
    $b = mediaFile('b.jpg', 'jpeg', 640, 480);
    $path = parse_url(route('admin.media.bulk-delete'), PHP_URL_PATH);

    $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-delete'), ['ids' => [$a->id]])->assertStatus(428);
    expect(Media::query()->count())->toBe(2);

    $token = mediaToken($this, $admin, $path);
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-delete'), ['ids' => [$a->id, $b->id], 'confirmation' => $token])
        ->assertOk()->assertJsonCount(2, 'deleted');
    expect(Media::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($a->path);
    Storage::disk('public')->assertMissing($b->variants['thumb']['path']);
    // the token is spent
    $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-delete'), ['ids' => [1], 'confirmation' => $token])->assertStatus(428);
    expect(Activity::query()->where('event', 'media.deleted')->count())->toBe(2);
});

it('blocks deleting a file that is in use and lists where; force clears the references', function () {
    $admin = mediaAdmin();
    $media = mediaFile('used.jpg');
    $owner = MediaOwner::query()->find(staff('editor')->id);
    $owner->syncMedia('cover', [$media->id]);
    $path = parse_url(route('admin.media.bulk-delete'), PHP_URL_PATH);

    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-delete'), ['ids' => [$media->id], 'confirmation' => mediaToken($this, $admin, $path)]);
    $response->assertStatus(409)->assertJsonPath('blocked.0.id', $media->id)->assertJsonPath('blocked.0.places.0.field', 'cover')->assertJsonPath('blocked.0.places.0.label', 'Owner '.$owner->id);
    expect(Media::query()->count())->toBe(1);
    Storage::disk('public')->assertExists($media->path);
    expect(fn () => app(DeleteMedia::class)($media))->toThrow(MediaInUse::class);

    $this->actingAs($admin, 'admin')->postJson(route('admin.media.bulk-delete'), ['ids' => [$media->id], 'force' => true, 'confirmation' => mediaToken($this, $admin, $path)])
        ->assertOk()->assertJsonPath('deleted.0', $media->id);
    expect(Media::query()->count())->toBe(0)->and($owner->fresh()->mediaFor('cover'))->toHaveCount(0);
    $log = Activity::query()->where('event', 'media.deleted')->latest('id')->first();
    expect($log->properties['forced'])->toBeTrue()->and($log->properties['usages_cleared'])->toBe(1);
});

it('keeps the order of a media field and forgets usages with the record', function () {
    $a = mediaFile('a.jpg');
    $b = mediaFile('b.jpg', 'jpeg', 640, 480);
    $owner = MediaOwner::query()->find(staff('editor')->id);
    $owner->syncMedia('gallery', [$b->id, $a->id, 99999]);
    expect($owner->mediaFor('gallery')->pluck('id')->all())->toBe([$b->id, $a->id]);
    $owner->syncMedia('gallery', [$a->id]);
    expect($owner->mediaFor('gallery')->pluck('id')->all())->toBe([$a->id]);
    MediaUsage::forget($owner);
    expect(DB::table('media_usages')->count())->toBe(0);
});

// ------------------------------------------------------------------------------------------------- settings / limit

it('caps the upload limit by the real PHP limits and explains it in plain words', function () {
    $limit = app(UploadLimit::class);
    app(Settings::class)->set('media.max_kb', 2097152); // 2 GB asked for
    expect($limit->effectiveBytes())->toBeLessThanOrEqual(max($limit->phpUpload(), $limit->phpPost(), 1) === 0 ? PHP_INT_MAX : max($limit->phpUpload(), $limit->phpPost()))
        ->and($limit->settingTooHigh())->toBeTrue();

    $admin = mediaAdmin();
    $html = $this->actingAs($admin, 'admin')->get(route('admin.media.index'))->assertOk()->getContent();
    expect($html)->toContain('data-limit-warning')->toContain('upload_max_filesize');
    app()->setLocale('sr');
    expect((string) __('codeart::media.limit.too_small.upload_max_filesize', ['limit' => '8 MB']))->toContain('hosting provajdera');

    expect(UploadLimit::bytes('8M'))->toBe(8388608)->and(UploadLimit::bytes('2G'))->toBe(2147483648)->and(UploadLimit::bytes('512K'))->toBe(524288);
});

it('saves the limits through the checked settings path (media.update)', function () {
    $admin = mediaAdmin();
    $this->actingAs($admin, 'admin')->put(route('admin.media.settings'), ['max_kb' => 2048, 'types' => ['jpg', 'pdf']])->assertRedirect();
    expect(app(Settings::class)->get('media.max_kb'))->toBe(2048)->and(app(Settings::class)->get('media.allowed_types'))->toBe(['jpg', 'pdf']);
    $this->actingAs($admin, 'admin')->put(route('admin.media.settings'), ['max_kb' => 2048, 'types' => ['svg']])->assertSessionHasErrors('types.0');
    $this->actingAs(mediaAdmin('editor'), 'admin')->put(route('admin.media.settings'), ['max_kb' => 5])->assertForbidden();
});
