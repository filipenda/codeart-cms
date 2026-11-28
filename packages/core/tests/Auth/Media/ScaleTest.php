<?php

require_once __DIR__.'/../../Support/media.php';

use Codeart\Core\Media\Media;
use Illuminate\Support\Facades\DB;

/** A large table: rows are inserted in bulk (no files), the list must stay a handful of indexed, bounded queries. */
beforeEach(function () {
    $rows = [];
    $now = now()->toDateTimeString();
    for ($i = 1; $i <= 6000; $i++) {
        $rows[] = ['disk' => 'public', 'path' => "uploads/2026/11/f{$i}.jpg", 'original_name' => ($i % 50 === 0 ? 'Cat-' : 'file-').$i.'.jpg', 'mime' => $i % 10 === 0 ? 'application/pdf' : 'image/jpeg',
            'size' => $i * 10, 'hash' => hash('sha256', (string) $i), 'folder_id' => null, 'uploaded_by' => $i % 3, 'created_at' => $now, 'updated_at' => $now];
        if (count($rows) === 1000) {
            DB::table('media')->insert($rows);
            $rows = [];
        }
    }
    DB::table('media_translations')->insert(['media_id' => 7, 'locale' => 'sr', 'alt' => 'zeleni travnjak']);
});

it('pages with a cursor in a bounded number of queries, whatever the table size', function () {
    $admin = mediaAdmin();
    DB::enableQueryLog();
    $first = $this->actingAs($admin, 'admin')->getJson(route('admin.media.items', ['per_page' => 48]))->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from `media`') || str_contains($q, 'media_translations'));
    DB::disableQueryLog();

    expect($first->json('items'))->toHaveCount(48)->and($first->json('next'))->not->toBeNull()
        ->and($queries)->toHaveCount(2)                                     // the page + the translations of the page
        ->and($queries->filter(fn ($q) => str_contains(strtolower($q), 'count('))->count())->toBe(0)
        ->and($queries->filter(fn ($q) => str_contains(strtolower($q), ' offset '))->count())->toBe(0);

    $second = $this->actingAs($admin, 'admin')->getJson(route('admin.media.items', ['per_page' => 48, 'cursor' => $first->json('next')]))->assertOk();
    expect($second->json('items.0.id'))->toBe($first->json('items.47.id') - 1);
});

it('filters by type, name or description, date, uploader and sorts, all on the server', function () {
    $admin = mediaAdmin();
    $get = fn (array $q) => $this->actingAs($admin, 'admin')->getJson(route('admin.media.items', $q + ['per_page' => 100]))->assertOk()->json('items');

    expect(collect($get(['type' => 'pdf']))->every(fn ($i) => $i['mime'] === 'application/pdf'))->toBeTrue()
        ->and(collect($get(['q' => 'Cat-']))->pluck('name')->every(fn ($n) => str_starts_with($n, 'Cat-')))->toBeTrue()
        ->and(collect($get(['q' => 'travnjak']))->pluck('id')->all())->toBe([7])
        ->and($get(['from' => '2999-01-01']))->toBe([])
        ->and(count($get(['to' => date('Y-m-d')])))->toBe(100)
        ->and(collect($get(['uploader' => 2]))->count())->toBe(100);
    $bySize = $get(['sort' => 'size']);
    expect($bySize[0]['size'])->toBeGreaterThan($bySize[1]['size']);
    $byName = $get(['sort' => 'name']);
    expect($byName[0]['name'])->toBe('Cat-100.jpg');
    expect(Media::query()->count())->toBe(6000);
});

it('caps a huge per_page and ignores junk filters', function () {
    $admin = mediaAdmin();
    $this->actingAs($admin, 'admin')->getJson(route('admin.media.items', ['per_page' => 100000, 'from' => 'not-a-date', 'sort' => 'drop table', 'folder' => 'x']))
        ->assertOk()->assertJsonCount(100, 'items');
});
