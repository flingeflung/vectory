<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('job_types', function (Blueprint $table) {
            $table->foreignId('job_group_id')->nullable()->after('tenant_id')->constrained('job_groups')->restrictOnDelete();
            $table->string('code', 30)->nullable()->after('job_group_id');
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('job_types', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropConstrainedForeignId('job_group_id');
            $table->dropColumn('code');
        });
        Schema::dropIfExists('job_groups');
    }
};
