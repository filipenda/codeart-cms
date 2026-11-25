<?php

use Illuminate\Support\Facades\DB;

it('runs on MariaDB, not SQLite', function () {
    expect(DB::connection()->getDriverName())->toBe('mariadb');
});
