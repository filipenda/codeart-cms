<?php

use Illuminate\Support\Facades\DB;

it('runs the test suite on MariaDB, not SQLite', function () {
    expect(DB::connection()->getDriverName())->toBe('mariadb');
    expect(DB::selectOne('select version() as v')->v)->toContain('MariaDB');
});
