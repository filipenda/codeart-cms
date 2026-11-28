<?php

namespace Codeart\Core\Media;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For a model that holds media (a cover image, a gallery): `$model->syncMedia('cover', [12])`, `$model->mediaFor('cover')`.
 * Records the usage, so the library can show where a file is used and refuses to delete it while it is.
 *
 * @mixin Model
 */
trait HasMedia
{
    public static function bootHasMedia(): void
    {
        static::deleted(function (Model $model): void {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                MediaUsage::forget($model);
            }
        });
    }

    /** @return MorphMany<MediaUsageRow, $this> */
    public function mediaUsages(): MorphMany
    {
        return $this->morphMany(MediaUsageRow::class, 'usable');
    }

    /** @param  list<int|string>  $ids  media ids in display order */
    public function syncMedia(string $field, array $ids): void
    {
        MediaUsage::sync($this, $field, array_map('intval', $ids));
    }

    /** @return Collection<int, Media> in the saved order */
    public function mediaFor(string $field): Collection
    {
        $ids = $this->mediaUsages()->where('field', $field)->orderBy('position')->pluck('media_id')->all();
        if ($ids === []) {
            return new Collection;
        }
        $byId = Media::query()->with('translations')->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn ($id) => $byId->get($id), $ids))));
    }
}
