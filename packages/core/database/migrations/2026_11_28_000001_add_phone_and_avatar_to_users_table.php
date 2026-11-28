<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profile fields: an optional phone number and the stored avatar path (relative to the branding disk). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable();
            $table->string('avatar_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar_path']);
        });
    }
};
