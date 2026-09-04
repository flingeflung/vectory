<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fehlende Felder für den vollen Illustrationsauftrag-Dialog (Vietto:
     * grafikerstellung.strBeschreibung/dtgFertigBis/valInitUserID/
     * valIllustratorID/valDoneUserID). valIllustratorFirmaID wird bewusst
     * NICHT übernommen - die Firma ergibt sich aus illustrator->company,
     * keine redundante Momentaufnahme nötig.
     */
    public function up(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->text('description')->nullable()->after('graphic_order_status_id');
            $table->date('due_date')->nullable()->after('description');
            $table->foreignId('initiated_by_person_id')->nullable()->after('due_date')->constrained('people')->nullOnDelete();
            $table->foreignId('illustrator_person_id')->nullable()->after('initiated_by_person_id')->constrained('people')->nullOnDelete();
            $table->foreignId('completed_by_person_id')->nullable()->after('done_at')->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initiated_by_person_id');
            $table->dropConstrainedForeignId('illustrator_person_id');
            $table->dropConstrainedForeignId('completed_by_person_id');
            $table->dropColumn(['description', 'due_date']);
        });
    }
};
