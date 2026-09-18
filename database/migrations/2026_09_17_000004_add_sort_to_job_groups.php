<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_groups', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0);
        });

        DB::table('job_groups')->orderBy('tenant_id')->orderBy('name')->get(['id', 'tenant_id'])
            ->groupBy('tenant_id')->each(function ($groups) {
                foreach ($groups->values() as $index => $group) {
                    DB::table('job_groups')->where('id', $group->id)->update(['sort' => $index]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_groups', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
