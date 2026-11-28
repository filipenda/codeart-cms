<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_invitations') || Schema::hasColumn('user_invitations', 'message')) {
            return;
        }
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->string('message', 500)->nullable()->after('roles'); // optional personal note shown in the mail
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_invitations', 'message')) {
            Schema::table('user_invitations', function (Blueprint $table) {
                $table->dropColumn('message');
            });
        }
    }
};
