<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('example_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        // One row per locale. Real columns, so they can be indexed, searched and sorted.
        Schema::create('example_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('example_item_id')->constrained('example_items')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->string('slug');
            $table->json('manual_fields')->nullable(); // attributes edited by hand in a derived locale
            $table->unique(['example_item_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('example_item_translations');
        Schema::dropIfExists('example_items');
    }
};
