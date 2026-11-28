<?php

namespace Codeart\Core\Media;

use Codeart\Core\Translations\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded file. The id is the stable reference (content and modules store the id, never the path), so the file
 * can be renamed, moved or replaced without breaking anything. `alt` and `title` are translatable.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string $hash
 * @property array<string, array{path: string, width: int, height: int, webp?: string}>|null $variants
 * @property int|null $folder_id
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 */
final class Media extends Model
{
    use SoftDeletes;
    use Translatable;

    protected $table = 'media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variants' => 'array', 'size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    /** @return list<string> */
    public function translatedAttributes(): array
    {
        return ['alt', 'title'];
    }

    public function slugSource(): string
    {
        return 'title';
    }

    /** @return class-string<MediaTranslation> */
    public function translationModel(): string
    {
        return MediaTranslation::class;
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /** @return HasMany<MediaUsageRow, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsageRow::class, 'media_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function url(): string
    {
        return self::publicUrl($this->disk, $this->path);
    }

    /** URL of a generated size (`thumb`, `medium`, `large`), the original when that size does not exist. */
    public function variantUrl(string $name, bool $webp = false): ?string
    {
        $variant = ($this->variants ?? [])[$name] ?? null;
        $path = $variant === null ? null : ($webp ? ($variant['webp'] ?? null) : $variant['path']);

        return $path !== null ? self::publicUrl($this->disk, $path) : ($webp ? null : $this->url());
    }

    /** `srcset` value of the generated sizes (original last), null when there is nothing but the original. */
    public function srcset(): ?string
    {
        if (! $this->isImage() || ($this->variants ?? []) === []) {
            return null;
        }
        $parts = [];
        foreach ($this->variants as $variant) {
            $parts[(int) $variant['width']] = self::publicUrl($this->disk, $variant['path']).' '.(int) $variant['width'].'w';
        }
        if ($this->width !== null) {
            $parts[$this->width] = $this->url().' '.$this->width.'w';
        }
        ksort($parts);

        return implode(', ', $parts);
    }

    /**
     * Everything a picker, a form field or the editor needs to show or insert the file.
     *
     * @return array<string, mixed>
     */
    public function toPicker(?string $locale = null): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => (string) ($this->translated('alt', $locale) ?? ''),
            'title' => (string) ($this->translated('title', $locale) ?? ''),
            'thumb' => $this->isImage() ? $this->variantUrl('thumb') : null,
            'srcset' => $this->srcset(),
            'folder_id' => $this->folder_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Root-relative for local files (an absolute URL built from APP_URL breaks when the site is opened under another host). */
    public static function publicUrl(string $disk, string $path): string
    {
        $url = Storage::disk($disk)->url($path);
        $base = rtrim((string) config('app.url'), '/');

        return $base !== '' && str_starts_with($url, $base.'/') ? substr($url, strlen($base)) : $url;
    }
}
