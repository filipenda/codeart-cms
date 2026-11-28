<?php

namespace Codeart\Core\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string $field
 * @property int $position
 */
class MediaUsageRow extends Model
{
    protected $table = 'media_usages';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @return MorphTo<Model, $this> */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
