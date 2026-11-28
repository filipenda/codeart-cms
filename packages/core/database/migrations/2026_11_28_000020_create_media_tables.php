<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->index(['parent_id', 'name']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('hash', 64);
            $table->json('variants')->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('hash');
            $table->index(['folder_id', 'id']);
            $table->index(['mime', 'id']);
            $table->index(['uploaded_by', 'id']);
            $table->index('created_at');
            $table->index('original_name');
        });

        Schema::create('media_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('alt', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('manual_fields')->nullable();
            $table->unique(['media_id', 'locale']);
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('usable_type', 190);
            $table->unsignedBigInteger('usable_id');
            $table->string('field', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unique(['media_id', 'usable_type', 'usable_id', 'field'], 'media_usages_unique');
            $table->index(['usable_type', 'usable_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_translations');
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
