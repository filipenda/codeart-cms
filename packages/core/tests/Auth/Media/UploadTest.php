<?php

require_once __DIR__.'/../../Support/media.php';

use Codeart\Core\Media\Actions\StoreMedia;
use Codeart\Core\Media\DuplicateMedia;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaRejected;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fake_disk('public');
    app(Settings::class)->set('media.max_kb', 10240);
});

function store(string $name, string $bytes, ?string $mime = null, string $dup = 'ask'): Media
{
    return app(StoreMedia::class)(upload($name, $bytes, $mime), null, null, $dup);
}

it('stores an image under uploads/YYYY/MM with a sanitized, random-suffixed name and keeps the original', function () {
    $media = store('Moja Slika (1).JPG', imageBytes('jpeg'));

    expect($media->path)->toMatch('#^uploads/\d{4}/\d{2}/moja-slika-1-[a-z0-9]{8}\.jpg$#')
        ->and($media->mime)->toBe('image/jpeg')
        ->and([$media->width, $media->height])->toBe([1000, 600])
        ->and($media->original_name)->toBe('Moja Slika (1).JPG')
        ->and(strlen($media->hash))->toBe(64);
    Storage::disk('public')->assertExists($media->path);
});

it('rejects by real content, not by extension or client MIME', function (string $name, string $bytes, ?string $mime, string $reason) {
    expect(fn () => store($name, $bytes, $mime))->toThrow(MediaRejected::class);
    try {
        store($name, $bytes, $mime);
    } catch (MediaRejected $e) {
        expect($e->reason)->toBe($reason);
    }
    expect(Media::query()->count())->toBe(0);
})->with([
    'php renamed to jpg, claims image/jpeg' => ['a.jpg', '<?php system($_GET["c"]); ?>', 'image/jpeg', 'type_not_allowed'],
    'html renamed to png' => ['a.png', '<html><script>alert(1)</script></html>', 'image/png', 'type_not_allowed'],
    'svg with script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml', 'dangerous_name'],
    'svg renamed to png' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/png', 'type_not_allowed'],
    'php file' => ['shell.php', '<?php echo 1;', 'application/x-php', 'dangerous_name'],
    'phtml' => ['shell.phtml', '<?php echo 1;', null, 'dangerous_name'],
    'phar' => ['x.phar', 'abc', null, 'dangerous_name'],
    'html file' => ['page.html', '<h1>x</h1>', 'text/html', 'dangerous_name'],
    'js file' => ['x.js', 'alert(1)', 'text/javascript', 'dangerous_name'],
    'double extension php.jpg' => ['shell.php.jpg', '<?php echo 1;', 'image/jpeg', 'dangerous_name'],
    'double extension on a real image' => ['photo.php.jpg', 'IMG', 'image/jpeg', 'dangerous_name'],
    'htaccess' => ['.htaccess', 'AddType application/x-httpd-php .jpg', null, 'dangerous_name'],
    'zero byte' => ['empty.jpg', '', 'image/jpeg', 'empty'],
]);

it('rejects a corrupt image and a truncated png', function () {
    $png = imageBytes('png');
    expect(fn () => store('broken.png', substr($png, 0, 40), 'image/png'))->toThrow(MediaRejected::class, '');
    expect(fn () => store('broken.jpg', "\xFF\xD8\xFF\xE0garbage", 'image/jpeg'))->toThrow(MediaRejected::class);
});

it('rejects a file larger than the effective limit', function () {
    app(Settings::class)->set('media.max_kb', 1);
    expect(fn () => store('big.jpg', imageBytes('jpeg', 800, 800)))->toThrow(MediaRejected::class);
});

it('rejects images with too many pixels', function () {
    config(['codeart.media.max_pixels' => 1000]);
    expect(fn () => store('big.png', imageBytes('png', 100, 100)))->toThrow(MediaRejected::class);
});

it('strips EXIF (GPS) from a jpeg', function () {
    $bytes = imageBytes('jpeg', exif: true);
    expect(str_contains($bytes, 'GPSLatitude'))->toBeTrue();
    $media = store('gps.jpg', $bytes);
    $stored = Storage::disk('public')->get($media->path);
    expect(str_contains($stored, 'GPSLatitude'))->toBeFalse()->and(str_contains($stored, "Exif\0\0"))->toBeFalse();
});

it('accepts png, webp, gif, pdf and a docx, and stores the type by what it IS', function () {
    expect(store('a.png', imageBytes('png'))->mime)->toBe('image/png')
        ->and(store('b.webp', imageBytes('webp'))->mime)->toBe('image/webp')
        ->and(store('c.gif', imageBytes('gif', 50, 50))->mime)->toBe('image/gif')
        ->and(store('d.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")->mime)->toBe('application/pdf')
        ->and(store('png-named-jpg.jpg', imageBytes('png', 40, 40))->path)->toEndWith('.png');

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->close();
    expect(store('Report.docx', (string) file_get_contents($tmp))->path)->toEndWith('.docx');

    // a zip pretending to be a docx is not one
    $fake = tempnam(sys_get_temp_dir(), 'fake');
    $zip->open($fake, ZipArchive::OVERWRITE);
    $zip->addFromString('payload.txt', 'x');
    $zip->close();
    expect(fn () => store('Report.docx', (string) file_get_contents($fake)))->toThrow(MediaRejected::class);
});

it('keeps zip off by default and lets a setting allow it, but never svg', function () {
    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'z');
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('a.txt', 'hello');
    $zip->close();
    $bytes = (string) file_get_contents($tmp);

    expect(fn () => store('a.zip', $bytes))->toThrow(MediaRejected::class);
    app(Settings::class)->set('media.allowed_types', ['jpg', 'zip']);
    expect(store('a.zip', $bytes)->mime)->toBe('application/zip');
    expect(fn () => app(Settings::class)->set('media.allowed_types', ['jpg', 'svg']))->toThrow(InvalidSettingValue::class);
});

it('detects duplicates by hash and can reuse or keep both', function () {
    $bytes = imageBytes('png', 64, 64);
    $first = store('one.png', $bytes);

    expect(fn () => store('two.png', $bytes))->toThrow(DuplicateMedia::class)
        ->and(store('two.png', $bytes, null, 'reuse')->id)->toBe($first->id)
        ->and(store('two.png', $bytes, null, 'keep')->id)->not->toBe($first->id)
        ->and(Media::query()->count())->toBe(2);
});

it('never writes outside the disk root, whatever the client file name says', function () {
    $media = store('../../../../etc/evil name.png', imageBytes('png', 40, 40));
    expect($media->path)->toStartWith('uploads/')->and($media->path)->not->toContain('..')
        ->and($media->original_name)->toBe('evil name.png');
});

it('writes a no-execute .htaccess into uploads/ on the first upload', function () {
    store('first.png', imageBytes('png', 30, 30));
    $htaccess = Storage::disk('public')->get('uploads/.htaccess');
    expect($htaccess)->toContain('php_flag engine off')->toContain('Require all denied')->toContain('nosniff');
});
