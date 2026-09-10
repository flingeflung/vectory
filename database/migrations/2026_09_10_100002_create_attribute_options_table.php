<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auswahlmöglichkeiten für Pulldown-Attribute (data_type='select') -
     * echt kundenseitig pflegbar, anders als Viettos hartkodierte
     * Pulldowns (siehe Vietto-Recherche zu Heftung/Farbigkeit). "value" ist
     * der stabile technische Wert (liegt so in projects.attributes),
     * "label" die frei änderbare Anzeigebezeichnung.
     */
    public function up(): void
    {
        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_options');
    }
};
