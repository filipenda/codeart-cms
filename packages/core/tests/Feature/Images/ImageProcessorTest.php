<?php

use Codeart\Core\Images\ImageFormat;
use Codeart\Core\Images\ImageProcessor;
use Codeart\Core\Images\InterventionImageProcessor;

/** Synthetic image: transparent canvas, an opaque rectangle at (x, y), optional faint halo around it. */
function artwork(int $canvasW, int $canvasH, int $w, int $h, int $x, int $y, ?int $haloAlpha = null, string $format = 'png'): string
{
    $img = imagecreatetruecolor($canvasW, $canvasH);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    if ($haloAlpha !== null) {
        imagefilledrectangle($img, $x - 10, $y - 10, $x + $w + 9, $y + $h + 9, imagecolorallocatealpha($img, 0, 0, 0, $haloAlpha));
    }
    imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($img, 200, 30, 30, 0));
    ob_start();
    $format === 'webp' ? imagewebp($img, null, 100) : imagepng($img);

    return (string) ob_get_clean();
}

function processor(): ImageProcessor
{
    return app(ImageProcessor::class);
}

function size(string $bytes): array
{
    $i = processor()->inspect($bytes);

    return [$i->width, $i->height];
}

it('is bound in the container', function () {
    expect(processor())->toBeInstanceOf(InterventionImageProcessor::class);
});

it('reads type and dimensions from the content', function () {
    $info = processor()->inspect(artwork(300, 200, 100, 40, 50, 60));

    expect([$info->mime, $info->width, $info->height])->toBe(['image/png', 300, 200])
        ->and(processor()->inspect('not an image'))->toBeNull()
        ->and(processor()->inspect('<svg xmlns="http://www.w3.org/2000/svg"/>'))->toBeNull();
});

it('trims transparent margins to the exact artwork box', function (string $format) {
    $trimmed = processor()->trimTransparent(artwork(300, 200, 100, 40, 120, 90, null, $format));

    expect($trimmed)->not->toBeNull()
        ->and(size($trimmed))->toBe([100, 40])
        ->and(processor()->inspect($trimmed)->mime)->toBe($format === 'png' ? 'image/png' : 'image/webp');
})->with(['png', 'webp']);

it('is not blocked by a faint shadow below the tolerance, but keeps a visible one', function () {
    // alpha 125 of 127 = ~1.6% opaque: ignored. alpha 60 = ~53% opaque: kept (artwork 100x40 + 10px halo each side).
    expect(size(processor()->trimTransparent(artwork(300, 200, 100, 40, 100, 80, 125))))->toBe([100, 40])
        ->and(size(processor()->trimTransparent(artwork(300, 200, 100, 40, 100, 80, 60))))->toBe([120, 60]);
});

it('trims wide and tall logos', function () {
    expect(size(processor()->trimTransparent(artwork(500, 300, 400, 20, 30, 140))))->toBe([400, 20])
        ->and(size(processor()->trimTransparent(artwork(500, 300, 20, 250, 240, 20))))->toBe([20, 250]);
});

it('keeps an artwork touching a corner and one touching all edges', function () {
    expect(size(processor()->trimTransparent(artwork(300, 200, 100, 40, 0, 0))))->toBe([100, 40])
        ->and(processor()->trimTransparent(artwork(100, 40, 100, 40, 0, 0)))->toBeNull();
});

it('leaves fully transparent, tiny-result, jpg and opaque images alone', function () {
    $transparent = imagecreatetruecolor(50, 50);
    imagealphablending($transparent, false);
    imagesavealpha($transparent, true);
    imagefill($transparent, 0, 0, imagecolorallocatealpha($transparent, 0, 0, 0, 127));
    ob_start();
    imagepng($transparent);
    $empty = (string) ob_get_clean();

    $opaque = imagecreatetruecolor(80, 60);
    ob_start();
    imagepng($opaque);
    $opaquePng = (string) ob_get_clean();
    ob_start();
    imagejpeg($opaque);
    $jpg = (string) ob_get_clean();

    expect(processor()->trimTransparent($empty))->toBeNull()
        ->and(processor()->trimTransparent(artwork(300, 200, 2, 2, 100, 100)))->toBeNull()
        ->and(processor()->trimTransparent($opaquePng))->toBeNull()
        ->and(processor()->trimTransparent($jpg))->toBeNull()
        ->and(processor()->trimTransparent('garbage'))->toBeNull();
});

it('keeps the artwork pixels unchanged after trimming', function () {
    $trimmed = processor()->trimTransparent(artwork(300, 200, 100, 40, 120, 90));
    $gd = imagecreatefromstring($trimmed);

    expect(imagecolorat($gd, 0, 0) & 0xFFFFFF)->toBe((200 << 16) | (30 << 8) | 30)
        ->and((imagecolorat($gd, 0, 0) >> 24) & 0x7F)->toBe(0);
});

it('resizes to fit without upscaling', function () {
    $big = artwork(800, 400, 800, 400, 0, 0);
    $small = artwork(100, 50, 100, 50, 0, 0);

    expect(size(processor()->fit($big, 400, 400)))->toBe([400, 200])
        ->and(size(processor()->fit($small, 400, 400)))->toBe([100, 50])
        ->and(size(processor()->fit(artwork(300, 600, 300, 600, 0, 0), 1000, 100)))->toBe([50, 100]);
});

it('encodes to another format', function () {
    $png = artwork(60, 30, 60, 30, 0, 0);

    expect(processor()->inspect(processor()->encode($png, ImageFormat::Webp, 80))->mime)->toBe('image/webp')
        ->and(processor()->inspect(processor()->encode($png, ImageFormat::Jpg, 80))->mime)->toBe('image/jpeg')
        ->and(processor()->inspect(processor()->encode($png, ImageFormat::Png))->mime)->toBe('image/png');
});

it('picks the driver: gd by default, imagick only when the extension exists, gd otherwise', function () {
    expect(InterventionImageProcessor::resolveDriver('gd'))->toBe('gd')
        ->and(InterventionImageProcessor::resolveDriver('nonsense'))->toBe('gd')
        ->and(InterventionImageProcessor::resolveDriver('imagick'))->toBe(extension_loaded('imagick') ? 'imagick' : 'gd')
        ->and((new InterventionImageProcessor('gd'))->driver())->toBe('gd');
});

it('trims the same with the imagick driver when available', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('imagick not installed');
    }
    $p = new InterventionImageProcessor('imagick');

    expect($p->driver())->toBe('imagick')
        ->and($p->inspect($p->trimTransparent(artwork(300, 200, 100, 40, 120, 90)))->width)->toBe(100);
});

it('is the only place that touches an image library', function () {
    $root = dirname(__DIR__, 3).'/src';
    $offenders = [];
    $pattern = '/\\bIntervention\\\\|\\bimage(create\\w*|png|jpeg|webp|gif|copy\\w*|crop\\w*|scale|resize\\w*|colorat|sx|sy|fill\\w*|destroy|palettetotruecolor|alphablending|savealpha)\\s*\\(|\\bnew\\s+\\\\?Imagick|\\bgetimagesize\\w*\\s*\\(|\\bimagick_/i';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/src/Images/')) {
            continue;
        }
        if (preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
            $offenders[] = str_replace($root.'/', '', $file->getPathname());
        }
    }
    expect($offenders)->toBe([]);
});

arch('Intervention is used only inside the Images namespace')
    ->expect('Intervention')
    ->toOnlyBeUsedIn('Codeart\Core\Images');
