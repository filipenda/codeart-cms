<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drafts and history of every publishable model. The LIVE content stays in the model's own tables.
        Schema::create('codeart_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->string('kind', 20)->default('draft'); // draft | published | restored
            $table->json('content');                      // {"fields": {...}, "translations": {"sr": {...}, "en": {...}}}
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['revisionable_type', 'revisionable_id', 'id'], 'codeart_revisions_owner');
        });

        // Preview links: only the SHA-256 of the token is stored, bound to model, revision and user.
        Schema::create('codeart_preview_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('revision_id');
            $table->string('locale', 12)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codeart_preview_tokens');
        Schema::dropIfExists('codeart_revisions');
    }
};
