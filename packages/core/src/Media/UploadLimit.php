<?php

namespace Codeart\Core\Media;

use Codeart\Core\Settings\Settings;

/**
 * The size a person can really upload: the smallest of the library setting, PHP's `upload_max_filesize` and `post_max_size`.
 * The setting can never raise it above what PHP accepts, and the screen explains which value to change.
 */
final class UploadLimit
{
    public function __construct(private readonly Settings $settings) {}

    public function effectiveBytes(): int
    {
        $limits = array_filter([$this->settingBytes(), $this->phpUpload(), $this->phpPost()], fn (int $b): bool => $b > 0);

        return $limits === [] ? 0 : min($limits);
    }

    public function settingBytes(): int
    {
        return max(1, (int) $this->settings->get('media.max_kb')) * 1024;
    }

    public function phpUpload(): int
    {
        return self::bytes((string) ini_get('upload_max_filesize'));
    }

    public function phpPost(): int
    {
        return self::bytes((string) ini_get('post_max_size'));
    }

    /** Which value is the bottleneck: `setting`, `upload_max_filesize` or `post_max_size`. */
    public function bottleneck(): string
    {
        $effective = $this->effectiveBytes();

        return match (true) {
            $this->settingBytes() === $effective => 'setting',
            $this->phpUpload() === $effective => 'upload_max_filesize',
            default => 'post_max_size',
        };
    }

    /** True when the library setting asks for more than PHP will ever deliver. */
    public function settingTooHigh(): bool
    {
        return $this->bottleneck() !== 'setting';
    }

    /** @return array{effective: string, setting: string, upload_max_filesize: string, post_max_size: string, bottleneck: string, too_high: bool} */
    public function describe(): array
    {
        return [
            'effective' => self::human($this->effectiveBytes()),
            'setting' => self::human($this->settingBytes()),
            'upload_max_filesize' => self::human($this->phpUpload()),
            'post_max_size' => self::human($this->phpPost()),
            'bottleneck' => $this->bottleneck(),
            'too_high' => $this->settingTooHigh(),
        ];
    }

    public static function bytes(string $ini): int
    {
        $ini = trim($ini);
        if ($ini === '' || $ini === '0' || $ini === '-1') {
            return 0; // unlimited
        }
        $number = (float) $ini;
        $unit = strtolower(substr($ini, -1));

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function human(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, 1).' GB',
            $bytes >= 1024 ** 2 => round($bytes / 1024 ** 2, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
