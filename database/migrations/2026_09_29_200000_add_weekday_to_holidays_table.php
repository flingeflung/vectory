<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekday')->nullable()->after('date');
        });

        DB::table('holidays')->update(['weekday' => DB::raw('WEEKDAY(date) + 1')]);

        Schema::table('holidays', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekday')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('weekday');
        });
    }
};
