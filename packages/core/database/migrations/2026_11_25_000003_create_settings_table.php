<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120);
            $table->string('group', 60)->index();
            // '*' = not translatable. A real marker (not NULL) so the unique index works on MariaDB.
            $table->string('locale', 12)->default('*');
            $table->longText('value')->nullable(); // JSON; secrets hold an encrypted string
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });

        // A lock applies to the whole key (all locales).
        Schema::create('settings_locks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_locks');
        Schema::dropIfExists('settings');
    }
};
