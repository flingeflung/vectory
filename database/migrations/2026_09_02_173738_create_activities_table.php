<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vorgänge-Log, entspricht Viettos vorgaenge-Tabelle - bewusst mit
     * echter "type"-Spalte statt Klartext-Musterkennung (siehe App\Enums\ActivityType),
     * da Vietto den Vorgangstyp nur durch Text-Pattern-Matching auf
     * txtVorgang erkennen kann, was bei Formulierungsänderungen zerbricht.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('message');
            $table->boolean('is_automatic')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
