<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schritt-Vorlage innerhalb eines Workflows, entspricht Viettos
     * workflowsteps2. Beim Zuweisen eines Workflows an ein Projekt werden
     * diese Schritte künftig in eine projekteigene Instanz-Tabelle kopiert
     * (analog Viettos workflowprogress2) - das folgt in einem späteren Schritt.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('title');
            $table->string('short_title')->nullable();
            $table->string('milestone_title')->nullable();
            $table->integer('sort')->default(0);
            $table->integer('duration_days')->default(0);
            $table->boolean('is_active')->default(true);
            // Achtung: KEIN Workflow-Lebenszyklus-Marker (dafür ist die separate
            // "Projektende"/"Projekt verworfen"-Konvention über intStatus
            // zuständig, siehe Farbrecherche). is_start/is_end legen fest,
            // welcher Teilschritt-Termin das Projekt-Start-/Enddatum liefert
            // (Vietto: blnIsStart/blnIsEnde, ajax_workflow_setstend.php).
            $table->boolean('is_start')->default(false);
            $table->boolean('is_end')->default(false);
            $table->boolean('is_market_launch')->default(false);
            $table->boolean('has_due_date')->default(false);
            $table->boolean('send_email')->default(false);
            $table->boolean('duration_editable')->default(true);
            $table->boolean('show_in_translation')->default(false);
            $table->string('js_function')->nullable();
            $table->string('js_function_param')->nullable();
            $table->text('description')->nullable();
            $table->text('email_text')->nullable();
            // Rohwert aus Vietto (strFktGrpIDMsgTsk), Zweck noch nicht
            // geklärt - bewusst nicht modelliert, nur als Referenz mitgeführt.
            $table->string('msg_task_function_group_ids')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
