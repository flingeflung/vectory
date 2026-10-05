<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->string('route_name');
            $table->string('ability')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['Grundlast', 'planung.grundlast', 'planning.view', 'Stunden pro Woche, die eine Person für Daueraufgaben außerhalb von Projekten reserviert hat.'],
            ['Feiertage', 'admin.feiertage', null, 'Feiertage der Organisation. An diesen Tagen entfällt die Arbeitszeit.'],
            ['Funktionsgruppen', 'admin.function-groups', null, 'Gruppen von Personen mit gleicher Aufgabe im Workflow, z. B. Illustration oder Lektorat.'],
            ['Einsatzplan', 'admin.workflows', null, 'Legt am Workflow fest, in welchem Zeitraum die geplanten Stunden einer Funktionsgruppe berücksichtigt werden.'],
            ['Aufwandsprofil', 'admin.projektschablonen', null, 'Vorlage für die geplanten Stunden eines Projekts je Funktionsgruppe.'],
            ['Rechte-Set', 'admin.rechte', null, 'Zusammenstellung von Rechten, die einer Person zugewiesen wird.'],
        ] as [$term, $route, $ability, $description]) {
            DB::table('glossary_terms')->insert(['term' => $term, 'route_name' => $route, 'ability' => $ability, 'description' => $description, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
