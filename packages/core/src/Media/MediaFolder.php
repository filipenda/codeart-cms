<?php

namespace Codeart\Core\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 */
class MediaFolder extends Model
{
    protected $table = 'media_folders';

    protected $guarded = ['id'];

    /** @return BelongsTo<MediaFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MediaFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** True when `$candidate` is this folder or sits below it (a folder must never be moved into itself). */
    public function isSelfOrAncestorOf(?int $candidate): bool
    {
        for ($depth = 0; $candidate !== null && $depth < 100; $depth++) {
            if ($candidate === $this->id) {
                return true;
            }
            $candidate = self::query()->whereKey($candidate)->value('parent_id');
            $candidate = $candidate === null ? null : (int) $candidate;
        }

        return false;
    }

    /**
     * The whole tree as a flat, ordered list with depth (folders are few; capped at 2000).
     *
     * @return list<array{id: int, parent_id: int|null, name: string, depth: int}>
     */
    public static function tree(): array
    {
        $rows = self::query()->orderBy('name')->limit(2000)->get(['id', 'parent_id', 'name']);
        $byParent = $rows->groupBy(fn (self $f): int => (int) $f->parent_id);
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent->get($parent, []) as $folder) {
                $out[] = ['id' => $folder->id, 'parent_id' => $folder->parent_id, 'name' => $folder->name, 'depth' => $depth];
                if ($depth < 20) {
                    $walk($folder->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);

        return $out;
    }
}
