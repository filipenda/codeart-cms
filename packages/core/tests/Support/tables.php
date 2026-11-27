<?php

use Codeart\Core\Tables\BulkAction;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\Filter;
use Codeart\Core\Tables\RowAction;
use Codeart\Core\Tables\TableDefinition;
use Codeart\Core\Tests\Support\TableItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Items table with a category relation, for the data table tests. */
function makeItemTables(): void
{
    Schema::create('tbl_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('tbl_items', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status', 20)->default('draft');
        $table->foreignId('category_id')->nullable();
        $table->unsignedInteger('views')->default(0);
        $table->boolean('featured')->default(false);
        $table->dateTime('published_at')->nullable();
    });
    DB::table('tbl_categories')->insert([['name' => 'News'], ['name' => 'Blog']]);
}

/** @param array<int, array<string, mixed>> $rows */
function seedItems(array $rows): void
{
    DB::table('tbl_items')->insert($rows);
}

/** `$count` generated items: ids 1..count, titles "Item 001" ..., statuses cycling, views = id * 10. */
function seedMany(int $count): void
{
    $statuses = ['draft', 'published', 'archived'];
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ['title' => sprintf('Item %03d', $i), 'status' => $statuses[$i % 3], 'category_id' => ($i % 2) + 1, 'views' => $i * 10, 'featured' => $i % 2 === 0, 'published_at' => sprintf('2026-01-%02d 10:00:00', ($i % 28) + 1)];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('tbl_items')->insert($chunk);
    }
}

function itemsTable(): DataTable
{
    return DataTable::make('items', TableItem::query())
        ->columns(
            Column::number('id', 'ID'),
            Column::titleSubtitle('title', 'Title')->using(fn (TableItem $i) => ['title' => $i->title, 'subtitle' => $i->category?->name]),
            Column::status('status', 'Status', ['draft' => ['Draft', 'neutral'], 'published' => ['Published', 'success'], 'archived' => ['Archived', 'warning']]),
            Column::number('views', 'Views'),
            Column::boolean('featured', 'Featured'),
            Column::date('published_at', 'Published'),
            Column::text('category', 'Category', 'category_id')->using(fn (TableItem $i) => $i->category?->name),
        )
        ->filters(Filter::status('status', 'Status', ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']), Filter::dateRange('published_at', 'Published'), Filter::boolean('featured', 'Featured'))
        ->with('category')
        ->defaultSort('id', 'asc');
}

function tableRequest(array $query = []): Request
{
    return Request::create('/admin/items', 'GET', $query);
}

/** @return list<string> titles on the page, in order */
function titles(object $result): array
{
    return $result->rows->pluck('title')->all();
}

/** Table definition for the endpoints, resolved by key. Static hooks let a test swap the actions. */
class ItemsDefinition implements TableDefinition
{
    /** @var list<int> ids the handler saw, in call order */
    public static array $seen = [];

    public static ?Closure $handler = null;

    public function table(): DataTable
    {
        return itemsTable()
            ->permission('items.view')
            ->export('items')
            ->rowActions(RowAction::edit(fn (TableItem $i) => '/items/'.$i->id.'/edit', 'items.edit'), RowAction::delete(fn (TableItem $i) => '/items/'.$i->id, 'items.delete'))
            ->bulkActions(
                BulkAction::make('publish', 'Publish', function (TableItem $item) {
                    self::$seen[] = $item->id;

                    return (self::$handler ?? fn () => true)($item);
                }, 'items.publish', 'Publish the selected items?'),
                BulkAction::make('open', 'Open', fn () => true),
            );
    }
}
