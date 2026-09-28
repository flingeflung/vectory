<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wochenstunden-Historie je Person (Ralf, 2026-09-28) - Grundlage der
     * personellen Ressourcenplanung. Ein einzelner statischer Wert
     * (people.weekly_hours) reicht nicht: die WoSt einer Person können sich
     * im Jahresverlauf mehrfach ändern (z.B. Reduzierung, später wieder
     * Erhöhung) - jede Jahresplanung, die nur den AKTUELLEN Wert kennt, würde
     * rückwirkend falsch. Deshalb je Person mehrere Zeilen mit Gültigkeits-
     * zeitraum statt einer einzigen Spalte.
     *
     * valid_from/valid_to beide nullable: der allererste Datensatz einer
     * Person hat i.d.R. keinen Start ("schon immer so") und kein Ende
     * ("gilt bis heute"). Jeder weitere Datensatz bekommt sein Ende erst
     * automatisch gesetzt, sobald der NÄCHSTE Datensatz angelegt wird
     * (siehe PersonController::storeWeeklyHours()) - deshalb rein anhängbare
     * Historie, keine nachträgliche Bearbeitung bestehender Zeilen.
     *
     * Nur für Personen MIT Login relevant (Ralf: "nur die arbeiten im
     * Projekt und buchen dort Stunden") - siehe Backfill unten.
     */
    public function up(): void
    {
        Schema::create('person_weekly_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 4, 1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'valid_from']);
        });

        // Backfill (Ralf, 2026-09-28): für jede Person MIT Login ein erster
        // Datensatz (kein Start, kein Ende) - eigener, schon gepflegter Wert
        // (people.weekly_hours) hat Vorrang vor dem Mandanten-Standardwert,
        // damit real abweichende Werte (z.B. reduzierte Stunden) NICHT durch
        // den generischen Standard überschrieben werden. Nur wenn eine
        // Person bislang gar keinen eigenen Wert hatte, greift der
        // Mandanten-Standard (tenants.default_weekly_hours).
        $rows = DB::table('people')
            ->join('users', 'users.person_id', '=', 'people.id')
            ->join('tenants', 'tenants.id', '=', 'people.tenant_id')
            ->select('people.id as person_id', 'people.tenant_id', DB::raw('COALESCE(people.weekly_hours, tenants.default_weekly_hours) as hours'))
            ->distinct()
            ->get();

        $now = now();
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('person_weekly_hours')->insert($chunk->map(fn ($row) => [
                'tenant_id' => $row->tenant_id,
                'person_id' => $row->person_id,
                'hours' => $row->hours,
                'valid_from' => null,
                'valid_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        // people.weekly_hours ist damit obsolet (Ralf, 2026-09-28: "Kannste
        // entfernen.") - komplett durch die Historie ersetzt.
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('weekly_hours');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->decimal('weekly_hours', 4, 1)->nullable()->after('remarks');
        });

        Schema::dropIfExists('person_weekly_hours');
    }
};
