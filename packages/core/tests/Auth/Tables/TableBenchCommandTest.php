<?php

use Illuminate\Support\Facades\Schema;

it('runs the data table benchmark on a small table and drops it again', function () {
    $this->artisan('codeart:bench:table', ['--rows' => 1000, '--runs' => 1, '--export-rows' => 300])
        ->expectsOutputToContain('first page')
        ->expectsOutputToContain('CSV export of the first 300 rows')
        ->expectsOutputToContain('Dropped codeart_bench_items')
        ->assertExitCode(0);

    expect(Schema::hasTable('codeart_bench_items'))->toBeFalse();
});

it('refuses to run in production without --force', function () {
    $this->app['env'] = 'production';

    $this->artisan('codeart:bench:table', ['--rows' => 1000])->expectsOutputToContain('Refusing to run in production')->assertExitCode(1);

    expect(Schema::hasTable('codeart_bench_items'))->toBeFalse();
});
