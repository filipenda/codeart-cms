<?php

namespace Codeart\Core\Modules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $package
 * @property bool $enabled
 * @property string|null $installed_version
 * @property Carbon|null $enabled_at
 */
class ModuleRecord extends Model
{
    protected $table = 'modules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
        ];
    }
}
