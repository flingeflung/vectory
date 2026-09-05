<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fester, von uns gepflegter Rechte-Katalog (kein Mandanten-Bezug -
     * Mandanten weisen nur zu, sie erfinden keine neuen Rechte). Wächst mit
     * Bedarf, absichtlich nicht auf Vorrat befüllt (siehe Konzept-
     * Diskussion mit Ralf, Analyse von Viettos rechte-Tabelle). Diese
     * Migration seedet die ersten beiden real gebrauchten Rechte.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        DB::table('permissions')->insert([
            [
                'key' => 'project.complete',
                'label' => 'Projekt/Workflow-Schritt auf Beendet oder Verworfen setzen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'illustration_order.edit_terms',
                'label' => 'Bei Illustrationsaufträgen Termin und Anzahl Bilder ändern',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
