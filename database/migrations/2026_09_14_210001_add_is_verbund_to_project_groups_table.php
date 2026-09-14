<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_groups', function (Blueprint $table) {
            $table->boolean('is_verbund')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_groups', function (Blueprint $table) {
            $table->dropColumn('is_verbund');
        });
    }
};
