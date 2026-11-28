<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First and last name next to `name`. `name` stays the composed full name (first + last, kept in sync when the profile is
 * saved) so sign-in, invitations and Fortify keep working. Both columns stay NULL until a person saves their profile;
 * until then the form pre-fills them by splitting `name` on the first space.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'first_name')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'first_name')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
