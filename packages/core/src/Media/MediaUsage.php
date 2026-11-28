<?php

namespace Codeart\Core\Media;

use Codeart\Core\Editor\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Where is a file used? One row per (media, record, field). Modules write it through the `HasMedia` trait, the rich-text
 * editor through `sync()` after saving; the library reads it to block a delete and to show the list.
 */
final class MediaUsage
{
    /**
     * Replaces the media of `$owner`'s `$field` with `$ids` (order kept). Unknown ids are ignored.
     *
     * @param  list<int>  $ids
     */
    public static function sync(Model $owner, string $field, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $existing = $ids === [] ? [] : Media::query()->whereIn('id', $ids)->pluck('id')->map(fn ($i): int => (int) $i)->all();
        $ids = array_values(array_filter($ids, fn (int $id): bool => in_array($id, $existing, true)));

        DB::transaction(function () use ($owner, $field, $ids): void {
            MediaUsageRow::query()->where('usable_type', $owner->getMorphClass())->where('usable_id', $owner->getKey())->where('field', $field)->delete();
            foreach ($ids as $position => $id) {
                MediaUsageRow::query()->create(['media_id' => $id, 'usable_type' => $owner->getMorphClass(), 'usable_id' => $owner->getKey(), 'field' => $field, 'position' => $position]);
            }
        });
    }

    /**
     * Records what the LIVE content of a publishable model uses: its media fields (ids in a column) and the images inside
     * its rich-text (`editor`) fields (`data-media-id`). Called after publishing.
     */
    public static function syncPublished(Model $model): void
    {
        if (! method_exists($model, 'formFields')) {
            return;
        }
        foreach (method_exists($model, 'mediaFields') ? array_keys($model->mediaFields()) : [] as $column) {
            self::sync($model, $column, array_values(array_filter((array) $model->getAttribute($column), 'is_numeric')));
        }
        foreach ($model->formFields() as $field => $type) {
            if ($type !== 'editor' || ! method_exists($model, 'translations')) {
                continue;
            }
            $ids = [];
            foreach ($model->translations()->get() as $row) {
                $ids = [...$ids, ...HtmlSanitizer::mediaIds((string) $row->getAttribute($field))];
            }
            self::sync($model, 'editor:'.$field, $ids);
        }
    }

    /** Removes every usage row of a record (call when the record is deleted). */
    public static function forget(Model $owner): void
    {
        MediaUsageRow::query()->where('usable_type', $owner->getMorphClass())->where('usable_id', $owner->getKey())->delete();
    }

    /**
     * Human readable places a file is used in (at most `$limit`).
     *
     * @return list<array{type: string, id: int, field: string, label: string, url: string|null}>
     */
    public static function places(Media $media, int $limit = 50): array
    {
        $out = [];
        foreach (MediaUsageRow::query()->where('media_id', $media->id)->limit($limit)->get() as $row) {
            $class = Relation::getMorphedModel($row->usable_type) ?? $row->usable_type;
            $record = class_exists($class) && is_subclass_of($class, Model::class) ? $class::query()->find($row->usable_id) : null;
            $label = $record !== null && method_exists($record, 'displayTitle') ? (string) $record->displayTitle() : '';
            $out[] = [
                'type' => class_basename($class),
                'id' => (int) $row->usable_id,
                'field' => $row->field,
                'label' => $label !== '' ? $label : class_basename($class).' #'.$row->usable_id,
                'url' => $record !== null && method_exists($record, 'adminUrl') ? $record->adminUrl() : null,
            ];
        }

        return $out;
    }

    public static function count(Media $media): int
    {
        return MediaUsageRow::query()->where('media_id', $media->id)->count();
    }
}
