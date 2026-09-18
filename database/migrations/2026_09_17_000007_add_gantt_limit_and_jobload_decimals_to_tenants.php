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
            $table->unsignedSmallInteger('gantt_max_projects')->default(50);
            $table->unsignedTinyInteger('jobload_decimals')->default(2);
        });

        DB::table('settings')->where('key', 'gantt_max_projects')
            ->get(['tenant_id', 'value'])->each(function ($setting) {
                $limit = filter_var($setting->value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
                if ($limit !== false) {
                    DB::table('tenants')->where('id', $setting->tenant_id)->update(['gantt_max_projects' => $limit]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['gantt_max_projects', 'jobload_decimals']);
        });
    }
};
