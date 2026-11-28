<?php

namespace Codeart\Core\Publishing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One saved state of a publishable model (a draft, a published snapshot or a restored one).
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property string $kind
 * @property array{fields?: array<string, mixed>, translations?: array<string, array<string, mixed>>} $content
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'codeart_revisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, array<string, mixed>> */
    public function translations(): array
    {
        return (array) ($this->content['translations'] ?? []);
    }

    /** @return array<string, mixed> */
    public function fields(): array
    {
        return (array) ($this->content['fields'] ?? []);
    }
}
