<?php

namespace Codeart\Core\Publishing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property int $revision_id
 * @property int $user_id
 * @property string|null $locale
 * @property Carbon $expires_at
 */
class PreviewToken extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'codeart_preview_tokens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }
}
