<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funktionsgruppen sind wieder reines Task-Routing, keine Rechte-Quelle
     * mehr (siehe PermissionTemplate) - eine Person kann in mehreren
     * Fktgrps sein, was bei Rechte-Vererbung über Fktgrps zu ungewollten
     * Rechten führte (z.B. ein PM in "Lektorat" aus Routing-Gründen hätte
     * automatisch Lektorat-Rechte bekommen).
     */
    public function up(): void
    {
        Schema::dropIfExists('function_group_permission');
    }

    public function down(): void
    {
        Schema::create('function_group_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['function_group_id', 'permission_id']);
        });
    }
};
