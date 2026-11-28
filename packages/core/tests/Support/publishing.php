<?php

use Codeart\Core\Publishing\Actions\PublishContent;
use Codeart\Core\Publishing\Actions\SaveDraft;
use Codeart\Core\Publishing\Publishables;
use Codeart\Core\Publishing\PublishStatus;
use Codeart\Core\Tests\Support\PubItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Table, permissions (editor: no publish) and the scheduler registry for the publishing tests. */
function makePubTables(): void
{
    Schema::create('pub_items', function (Blueprint $table) {
        $table->id();
        $table->string('status', 20)->default('draft');
        $table->timestamp('published_at')->nullable();
        $table->timestamp('scheduled_at')->nullable();
        $table->unsignedBigInteger('draft_revision_id')->nullable();
        $table->timestamps();
    });
    Schema::create('pub_item_translations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('pub_item_id')->constrained('pub_items')->cascadeOnDelete();
        $table->string('locale', 12);
        $table->string('title');
        $table->text('body')->nullable();
        $table->string('slug');
        $table->json('manual_fields')->nullable();
        $table->unique(['pub_item_id', 'locale']);
        $table->unique(['locale', 'slug']);
    });
    foreach (['view', 'create', 'update', 'publish', 'delete'] as $action) {
        Permission::findOrCreate("pubitems.{$action}", 'admin');
    }
    Role::findByName('editor', 'admin')->givePermissionTo('pubitems.view', 'pubitems.update');
    app(Publishables::class)->register(PubItem::class);
}

function pubItem(array $sr = ['title' => 'Prva', 'body' => 'Tekst'], bool $publish = true): PubItem
{
    $item = PubItem::create(['status' => PublishStatus::Draft]);
    app(SaveDraft::class)($item, ['translations' => ['sr' => $sr]]);
    if ($publish) {
        app(PublishContent::class)($item->fresh());
    }

    return $item->fresh();
}
