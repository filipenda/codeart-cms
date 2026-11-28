<?php

use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Http\UploadedFile;

/** Real image bytes made with GD (tests may touch GD; the arch rule only guards src/stubs). */
function imageBytes(string $type = 'jpeg', int $w = 1000, int $h = 600, bool $exif = false): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 40, 40));
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 20, 20, 200));
    ob_start();
    match ($type) {
        'png' => imagepng($im),
        'webp' => imagewebp($im, null, 80),
        'gif' => imagegif($im),
        default => imagejpeg($im, null, 85),
    };
    $bytes = (string) ob_get_clean();
    if ($exif && $type === 'jpeg') {
        // An APP1 "Exif" segment with a GPS-looking payload, inserted right after the SOI marker.
        $payload = "Exif\0\0".'MM'."\0*\0\0\0\x08GPSLatitude=44.8125;GPSLongitude=20.4612";
        $bytes = substr($bytes, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($bytes, 2);
    }

    return $bytes;
}

function upload(string $name, string $bytes, ?string $mime = null): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cam');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, $mime, null, true);
}

function mediaAdmin(string $role = 'admin'): AdminTestUser
{
    app(Settings::class)->set('security.2fa.mode', 'off');

    return staff($role);
}
