<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Mitteilungen bekommen einen Titel; der Text wird optional (Ralf, 2026-10-10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('title', 120)->default('')->after('id');
            $table->string('text', 500)->nullable()->change();
        });

        DB::table('announcements')->where('title', '')->update(['title' => DB::raw('LEFT(text, 120)')]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
