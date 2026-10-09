<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Beschreibung einer Mail-Vorlage (Zweck der Mail), wird nicht mitgesendet (Ralf, 2026-10-09). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
