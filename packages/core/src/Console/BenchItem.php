<?php

namespace Codeart\Core\Console;

use Illuminate\Database\Eloquent\Model;

/** Row of the temporary table of `codeart:bench:table`. */
final class BenchItem extends Model
{
    protected $table = TableBenchCommand::BENCH_TABLE;

    public $timestamps = false;
}
