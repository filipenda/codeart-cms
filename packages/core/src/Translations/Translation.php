<?php

namespace Codeart\Core\Translations;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for a translation row (`<model>_translations`). Columns: the owner's foreign key,
 * `locale`, the translated attributes, `slug` (unique per locale), `manual_fields` (JSON list).
 *
 * @property string $locale
 * @property list<string>|null $manual_fields attributes edited by hand in a derived locale
 */
abstract class Translation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['manual_fields' => 'array'];
    }
}
