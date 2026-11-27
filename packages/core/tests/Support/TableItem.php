<?php

namespace Codeart\Core\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableItem extends Model
{
    protected $table = 'tbl_items';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['featured' => 'boolean', 'published_at' => 'datetime'];

    /** @return BelongsTo<TableCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TableCategory::class, 'category_id');
    }
}
