<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `theme_preference` NULL = "not chosen": the browser's choice or the site default applies. 'auto' is a real choice now. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'theme_preference')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_preference', 10)->nullable()->default(null)->change();
        });
        DB::table('users')->where('theme_preference', 'auto')->update(['theme_preference' => null]);
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'theme_preference')) {
            DB::table('users')->whereNull('theme_preference')->update(['theme_preference' => 'auto']);
            Schema::table('users', function (Blueprint $table) {
                $table->string('theme_preference', 10)->default('auto')->nullable(false)->change();
            });
        }
    }
};
