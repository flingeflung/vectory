<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('verbund_rolle')->nullable()->after('creation_type');
            $table->foreignId('hauptprojekt_id')->nullable()->after('verbund_rolle')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hauptprojekt_id');
            $table->dropColumn('verbund_rolle');
        });
    }
};
