<?php

namespace Codeart\Core\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class TableCategory extends Model
{
    protected $table = 'tbl_categories';

    protected $guarded = [];

    public $timestamps = false;
}
