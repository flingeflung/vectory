<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urlaubstage-Historie je Person (Ralf, 2026-09-28) - gleiche Systematik
     * wie die Wochenstunden-Historie (person_weekly_hours): fließt zusammen
     * mit den Wochenstunden und den Arbeitstagen des Jahres in die
     * Jahresstunden-Berechnung der personellen Ressourcenplanung ein, kann
     * sich ebenso im Zeitverlauf ändern (z.B. mehr Urlaub nach X Jahren
     * Betriebszugehörigkeit). Rein anhängbare Historie, gleiche Regeln wie
     * dort (siehe PersonController::storeVacationDays()).
     *
     * Neuer Mandanten-Standardwert default_vacation_days (Default: 30 Tage,
     * analog default_weekly_hours) - Backfill hier für ALLE bestehenden
     * Login-Personen, da es (anders als bei den Wochenstunden) noch keinen
     * vorher gepflegten Einzelwert gab, den man bevorzugen müsste.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('default_vacation_days', 4, 1)->default(30.0)->after('default_weekly_hours');
        });

        Schema::create('person_vacation_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->decimal('days', 4, 1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'valid_from']);
        });

        $now = now();
        $rows = DB::table('people')
            ->join('users', 'users.person_id', '=', 'people.id')
            ->join('tenants', 'tenants.id', '=', 'people.tenant_id')
            ->select('people.id as person_id', 'people.tenant_id', 'tenants.default_vacation_days as days')
            ->distinct()
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('person_vacation_days')->insert($chunk->map(fn ($row) => [
                'tenant_id' => $row->tenant_id,
                'person_id' => $row->person_id,
                'days' => $row->days,
                'valid_from' => null,
                'valid_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('person_vacation_days');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('default_vacation_days');
        });
    }
};
