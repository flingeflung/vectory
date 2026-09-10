<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-10 (Selbstkorrektur): Betreff gehört doch zur Vorlage,
     * nicht erst in den Anwendungsfall (anders als Empfänger/CC, die
     * weiterhin bewusst außen vor bleiben - siehe create_mail_templates_
     * table-Migration).
     */
    public function up(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->string('subject')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};
