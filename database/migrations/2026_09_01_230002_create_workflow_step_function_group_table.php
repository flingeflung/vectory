<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Funktionsgruppe(n) einen Workflow-Schritt ausführen (Viettos
     * strFktGrpID, semikolon-getrennt bei mehreren) - z.B. für parallel
     * ausführbare Schritte, die von unterschiedlichen Gruppen bearbeitet
     * werden.
     */
    public function up(): void
    {
        Schema::create('workflow_step_function_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workflow_step_id', 'function_group_id'], 'wfs_function_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_function_group');
    }
};
