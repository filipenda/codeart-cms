<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Display time zone per user (IANA identifier); NULL = the site zone. Dates stay UTC in the database. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'timezone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'timezone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
