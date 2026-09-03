<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Ausgeblendet"-Zustand einer Aufgabe, pro Benutzer (nicht pro Person -
     * die Sichtbarkeits-Einstellung gehört der Sicht des gerade
     * Betrachtenden, nicht dem eigentlichen Aufgaben-Inhaber; relevant z.B.
     * wenn später jemand mit Admin-Rechten die Aufgaben anderer einsieht).
     * Entspricht Viettos aufgaben_vis - Existenz einer Zeile = ausgeblendet,
     * bewusst ohne zusätzliches blnIsHidden-Flag (Vietto hatte dort keinen
     * Unique-Index und damit potenzielle Dopplungen - hier direkt sauber).
     */
    public function up(): void
    {
        Schema::create('task_visibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_visibilities');
    }
};
