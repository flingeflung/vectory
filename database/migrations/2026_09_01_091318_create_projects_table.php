<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Herkunftsverweis auf die Vietto-PN (Referenzsystem), kein fachlicher Schlüssel in Vectory.
            $table->string('source_pn', 6)->nullable();

            $table->string('title');
            $table->string('codename')->nullable();
            $table->string('initiator')->nullable();
            $table->string('system_model')->nullable();
            $table->string('material_number')->nullable();
            $table->string('format')->nullable();

            // Rohcodes aus Vietto (projIDmain/projIDsub), noch ohne eigene Lookup-Tabelle.
            $table->smallInteger('project_type_main')->nullable();
            $table->smallInteger('project_type_sub')->nullable();

            $table->smallInteger('version')->nullable();

            // 0=geplant, 1=in Bearbeitung, 2=beendet, 3=verworfen (übernommen aus Vietto intBearbStatus).
            $table->tinyInteger('status')->default(0);
            $table->boolean('archived')->default(false);
            $table->boolean('localization')->default(false);

            $table->date('publication_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'source_pn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
