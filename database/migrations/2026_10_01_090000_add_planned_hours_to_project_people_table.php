<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_people', function (Blueprint $table) {
            $table->decimal('planned_hours', 8, 2)->nullable()->after('is_primary');
        });

        DB::table('project_people')->select('project_id', 'function_group_id')
            ->distinct()->orderBy('project_id')->orderBy('function_group_id')
            ->get()->each(function ($group): void {
                $hours = DB::table('project_function_group_hours')
                    ->where('project_id', $group->project_id)
                    ->where('function_group_id', $group->function_group_id)
                    ->value('planned_hours');

                if ($hours === null) {
                    $templateId = DB::table('projects')->where('id', $group->project_id)->value('project_template_id');
                    $hours = $templateId
                        ? DB::table('project_template_function_group')->where('project_template_id', $templateId)
                            ->where('function_group_id', $group->function_group_id)->value('planned_hours')
                        : null;
                }

                $ids = DB::table('project_people')->where('project_id', $group->project_id)
                    ->where('function_group_id', $group->function_group_id)->orderBy('id')->pluck('id');
                $count = $ids->count();
                if ($hours !== null && $count > 0) {
                    $share = round((float) $hours / $count, 2);
                    $ids->each(function (int $id, int $index) use ($count, $hours, $share): void {
                        DB::table('project_people')->where('id', $id)->update([
                            'planned_hours' => $index === $count - 1
                                ? round((float) $hours - $share * ($count - 1), 2)
                                : $share,
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        Schema::table('project_people', function (Blueprint $table) {
            $table->dropColumn('planned_hours');
        });
    }
};
