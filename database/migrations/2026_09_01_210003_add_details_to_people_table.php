<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('legacy_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->foreignId('company_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('legacy_role_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('legacy_role_id');
            $table->date('start_date')->nullable()->after('last_login_at');
            $table->date('end_date')->nullable()->after('start_date');
            $table->text('remarks')->nullable()->after('end_date');
            $table->string('language', 5)->nullable()->after('remarks');
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('name')->after('legacy_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('legacy_role_id');
            $table->dropColumn(['first_name', 'last_name', 'last_login_at', 'start_date', 'end_date', 'remarks', 'language']);
        });
    }
};
