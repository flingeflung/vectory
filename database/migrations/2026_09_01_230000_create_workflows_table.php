<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow-Vorlage (Muster-WF), mandantenscoped. Entspricht Viettos
     * workflows - bewusst NUR intTyp=2 ("neues" System ab 2018), das alte
     * System (intTyp=1) wird auf Wunsch nicht übernommen. superseded_by_id
     * verweist auf den WF, der diesen ersetzt hat (Viettos valCopyUpdateID) -
     * alte Versionen bleiben trotzdem importiert/lesbar, da bestehende
     * Projekte weiter darauf zeigen können.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('short_name', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->foreignId('superseded_by_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
