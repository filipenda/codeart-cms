<?php

use Codeart\Core\Branding\BrandingImageUploader;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => fake_disk('public'));

function uploader(): BrandingImageUploader
{
    return app(BrandingImageUploader::class);
}

it('accepts png, jpg and webp and stores them under a random name', function (string $name, string $ext) {
    $path = uploader()->store('theme.logo_light', UploadedFile::fake()->image($name, 200, 80));

    expect($path)->toMatch('#^branding/[A-Za-z0-9]{40}\.'.$ext.'$#')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(app(Settings::class)->get('theme.logo_light'))->toBe($path)
        ->and($path)->not->toContain(pathinfo($name, PATHINFO_FILENAME));
})->with([['Logo.png', 'png'], ['logo.jpg', 'jpg'], ['logo.jpeg', 'jpg'], ['logo.webp', 'webp']]);

it('uses the detected type for the extension, not the client name', function () {
    $png = UploadedFile::fake()->image('real.png', 20, 20);
    $disguised = new UploadedFile($png->getRealPath(), 'weird.webp', 'image/webp', null, true);

    expect(uploader()->store('theme.favicon', $disguised))->toEndWith('.png');
});

it('rejects svg', function () {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    expect(fn () => uploader()->store('theme.logo_light', $svg))->toThrow(InvalidSettingValue::class, 'only PNG, JPG and WebP');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects a php file renamed to png', function () {
    $php = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "pwned";');

    expect(fn () => uploader()->store('theme.logo_light', $php))->toThrow(InvalidSettingValue::class)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(app(Settings::class)->has('theme.logo_light'))->toBeFalse();
});

it('rejects a gif even when named png', function () {
    $gif = UploadedFile::fake()->image('anim.gif', 10, 10);
    $renamed = new UploadedFile($gif->getRealPath(), 'anim.png', 'image/png', null, true);

    expect(fn () => uploader()->store('theme.logo_light', $renamed))->toThrow(InvalidSettingValue::class);
});

it('rejects oversized files and dimensions', function () {
    expect(fn () => uploader()->store('theme.logo_light', UploadedFile::fake()->image('big.png', 10, 10)->size(2049)))
        ->toThrow(InvalidSettingValue::class, 'larger than 2048 KB')
        ->and(fn () => uploader()->store('theme.logo_light', UploadedFile::fake()->image('wide.png', 4097, 10)))
        ->toThrow(InvalidSettingValue::class, '4096x4096');

    config(['codeart.branding.max_kb' => 1]);
    expect(fn () => uploader()->store('theme.logo_light', UploadedFile::fake()->image('big.png', 10, 10)->size(2)))->toThrow(InvalidSettingValue::class);
});

it('deletes the old file when replaced', function () {
    $first = uploader()->store('theme.logo_light', UploadedFile::fake()->image('a.png', 10, 10));
    $second = uploader()->store('theme.logo_light', UploadedFile::fake()->image('b.jpg', 10, 10));

    expect($second)->not->toBe($first)
        ->and(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists($second))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('removes the image and returns to the default', function () {
    $path = uploader()->store('theme.logo_dark', UploadedFile::fake()->image('a.png', 10, 10));
    uploader()->remove('theme.logo_dark');

    expect(Storage::disk('public')->exists($path))->toBeFalse()->and(app(Settings::class)->get('theme.logo_dark'))->toBeNull();
});

it('does not delete files outside the branding folder', function () {
    Storage::disk('public')->put('other/keep.png', 'x');
    app(Settings::class)->set('theme.logo_light', 'other/keep.png');
    uploader()->store('theme.logo_light', UploadedFile::fake()->image('a.png', 10, 10));

    expect(Storage::disk('public')->exists('other/keep.png'))->toBeTrue();
});

it('only accepts uploads for image settings and honors the configured disk and folder', function () {
    fake_disk('branding-disk');
    config(['codeart.branding.disk' => 'branding-disk', 'codeart.branding.directory' => 'brand/x']);

    expect(fn () => uploader()->store('theme.primary', UploadedFile::fake()->image('a.png')))->toThrow(InvalidSettingValue::class);
    $path = uploader()->store('theme.logo_light', UploadedFile::fake()->image('a.png'));

    expect($path)->toStartWith('brand/x/')->and(Storage::disk('branding-disk')->exists($path))->toBeTrue()
        ->and(uploader()->url('theme.logo_light'))->toBeString();
});

it('checks permission and lock for uploads through the manager', function () {
    $manager = app(SettingsManager::class);
    $file = fn () => UploadedFile::fake()->image('a.png', 10, 10);

    expect(fn () => $manager->uploadImage(userWith('editor'), 'theme.favicon', $file()))->toThrow(AuthorizationException::class);

    app(Settings::class)->lock('theme.favicon');
    expect(fn () => $manager->uploadImage(userWith('admin'), 'theme.favicon', $file()))->toThrow(AuthorizationException::class);
    expect($manager->uploadImage(userWith('super-admin'), 'theme.favicon', $file()))->toStartWith('branding/');
});

it('serves the trimmed logo and keeps the original next to it, then removes both', function () {
    $img = imagecreatetruecolor(300, 200);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagefilledrectangle($img, 100, 80, 199, 119, imagecolorallocatealpha($img, 10, 10, 10, 0));
    $tmp = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($img, $tmp);

    $path = uploader()->store('theme.logo_light', new UploadedFile($tmp, 'logo.png', 'image/png', null, true));
    $original = preg_replace('/\.png$/', '.original.png', $path);
    $disk = Storage::disk('public');

    expect(getimagesizefromstring($disk->get($path))[0])->toBe(100)
        ->and(getimagesizefromstring($disk->get($original))[0])->toBe(300)
        ->and(app(Settings::class)->get('theme.logo_light'))->toBe($path);

    uploader()->remove('theme.logo_light');
    expect($disk->allFiles())->toBe([]);
    @unlink($tmp);
});

it('leaves an opaque upload untouched and stores no original copy', function () {
    $path = uploader()->store('theme.logo_light', UploadedFile::fake()->image('a.png', 200, 80));

    expect(Storage::disk('public')->allFiles())->toBe([$path]);
});
