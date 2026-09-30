<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateString();

        DB::table('people')
            ->where('is_absent', true)
            ->orderBy('id')
            ->each(function ($person) use ($today): void {
                $endsOn = $person->absent_until && $person->absent_until >= $today
                    ? $person->absent_until
                    : $today;

                $alreadyExists = DB::table('calendar_entries')
                    ->where('person_id', $person->id)
                    ->where('type', 'absence')
                    ->whereDate('starts_on', '<=', $today)
                    ->whereDate('ends_on', '>=', $today)
                    ->exists();

                if (! $alreadyExists) {
                    DB::table('calendar_entries')->insert([
                        'person_id' => $person->id,
                        'created_by_user_id' => null,
                        'type' => 'absence',
                        'starts_on' => $today,
                        'ends_on' => $endsOn,
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Reine, verlustfreie Datenübernahme: Die alten Personenfelder bleiben
        // als ungenutzte Altdaten erhalten, erzeugte Kalendereinträge ebenfalls.
    }
};
