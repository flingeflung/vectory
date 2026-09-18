<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('jobload_time_grid')->default(15);
        });

        DB::table('tenants')->update([
            'jobload_time_grid' => DB::raw('CASE jobload_decimals WHEN 0 THEN 60 WHEN 1 THEN 30 ELSE 15 END'),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('jobload_decimals');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('jobload_decimals')->default(2);
        });

        DB::table('tenants')->update([
            'jobload_decimals' => DB::raw('CASE jobload_time_grid WHEN 60 THEN 0 WHEN 30 THEN 1 ELSE 2 END'),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('jobload_time_grid');
        });
    }
};
