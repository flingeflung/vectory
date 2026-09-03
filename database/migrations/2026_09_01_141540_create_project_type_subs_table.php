<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projektart (Unterart), immer einer Hauptart zugeordnet. Entspricht
     * Viettos projartsub. "legacy_id" verweist auf den ursprünglichen
     * Vietto-Code (valID) und dient dem Abgleich mit
     * projects.project_type_sub, das weiterhin den rohen Code speichert.
     */
    public function up(): void
    {
        Schema::create('project_type_subs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_type_main_id')->constrained('project_type_mains')->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_type_subs');
    }
};
