<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonVacationDays;
use App\Models\PersonWeeklyHours;
use App\Models\PlanningBaseLoad;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Neuer Hauptnavigationspunkt "Planung" (Ralf, 2026-09-28) - rechtegesteuert
 * (planning.view), Design wie der Admin-Bereich (Reiter oben, siehe
 * App\Support\PlanningNav/planning-layout.blade.php). Erster Reiter
 * "Stunden": Jahresstunden-Kapazität je Person, aus Wochenstunden- und
 * Urlaubstage-Historie (siehe Person::weeklyHours()/vacationDays()) und den
 * (reinen Wochentags-)Arbeitstagen des gewählten Jahres berechnet.
 */
class PlanningController extends Controller
{
    private const HOURS_SORTABLE_COLUMNS = ['name', 'department', 'wost', 'workdays', 'vacation_hours', 'annual_hours'];

    public function grundlast(Request $request): View
    {
        abort_unless($request->user()->can('planning.view'), 403);

        $tenantId = CurrentTenant::id();
        $currentYear = (int) now()->year;
        $baseLoadYears = PlanningBaseLoad::query()->where('tenant_id', $tenantId)->pluck('year');
        $years = $baseLoadYears
            ->merge([$currentYear, $currentYear + 1, $currentYear + 2])
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
        $year = $request->integer('year', $currentYear);
        if (! in_array($year, $years, true)) {
            $year = $currentYear;
        }

        $baseLoads = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->orderBy('valid_from')
            ->orderBy('name')
            ->get();
        $previousYearCount = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year - 1)
            ->count();
        $tenant = CurrentTenant::current();

        return view('planning.grundlast', compact('years', 'year', 'baseLoads', 'previousYearCount', 'tenant'));
    }

    public function arbeitszeit(Request $request): View
    {
        abort_unless($request->user()->can('planning.view'), 403);

        $tenantId = CurrentTenant::id();
        $peopleQuery = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($request->user()->role)
            ->visibleInTenant($tenantId)
            ->whereHas('user')
            ->where('resource_planning', true);

        $personIds = (clone $peopleQuery)->pluck('id');
        [$years, $year] = $this->planningYears($personIds, $request);
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();

        // Exakt dieselbe fachliche Auswahl wie in der Stunden-Tabelle:
        // Personen ohne einen im gewählten Jahr gültigen Wochenstundenwert
        // werden hier ebenfalls nicht angeboten.
        $people = (clone $peopleQuery)
            ->with(['weeklyHours' => fn ($query) => $query->orderByRaw('valid_from IS NOT NULL')->orderBy('valid_from')])
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->filter(fn (Person $person) => $this->overlappingWeeklyHours($person, $yearStart, $yearEnd)->isNotEmpty())
            ->values();

        $personId = $request->integer('person');
        $person = $people->firstWhere('id', $personId) ?? $people->first();
        $highestWeeklyHours = (float) ($people
            ->flatMap(fn (Person $item) => $this->overlappingWeeklyHours($item, $yearStart, $yearEnd))
            ->max(fn (PersonWeeklyHours $row) => (float) $row->hours) ?? 0);
        $yMax = (int) ceil($highestWeeklyHours / 10) * 10;
        $yMax = max(10, $yMax);
        $points = collect();

        if ($person) {
            $daysInYear = $yearStart->diffInDays($yearEnd) + 1;
            $points = $this->overlappingWeeklyHours($person, $yearStart, $yearEnd)
                ->map(function (PersonWeeklyHours $row) use ($yearStart) {
                    $start = $row->valid_from
                        ? CarbonImmutable::parse($row->valid_from->toDateString())->max($yearStart)
                        : $yearStart;

                    return [
                        'x' => $yearStart->diffInDays($start),
                        'y' => (float) $row->hours,
                    ];
                })
                ->values();

            if ($points->isNotEmpty()) {
                $points->push(['x' => $daysInYear, 'y' => $points->last()['y'], 'terminal' => true]);
            }
        }

        return view('planning.arbeitszeit', compact('years', 'year', 'people', 'person', 'points', 'yMax'));
    }

    public function stunden(Request $request): View
    {
        abort_unless($request->user()->can('planning.view'), 403);

        $tenantId = CurrentTenant::id();
        $viewerRole = $request->user()->role;

        // Gleiche Sichtbarkeit wie die Personenliste (Ralf: "auf die die
        // Person Zugriff hat, die sich das ansieht") - Heimat-Personen des
        // aktiven Kunden plus freigeschaltete Personen, Superadmins bleiben
        // für alle anderen unsichtbar. Nur Login-Personen: nur die haben
        // eine Wochenstunden-/Urlaubstage-Historie (siehe Migrationen).
        $peopleQuery = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($viewerRole)
            ->visibleInTenant($tenantId)
            ->whereHas('user');

        $personIds = (clone $peopleQuery)->where('resource_planning', true)->pluck('id');

        // Jahre fürs Dropdown: alle Jahre, die irgendein Wochenstunden-
        // Datensatz dieser (sichtbaren) Personen berührt (Ralf, 2026-09-28:
        // "Jahre, für die [Wochenstunden-]Planungsdaten vorliegen") - plus
        // immer das laufende Jahr, auch wenn noch nie ein Datum gepflegt
        // wurde (der ursprüngliche "schon immer"-Datensatz deckt es ja ab).
        [$years, $year] = $this->planningYears($personIds, $request);

        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $totalWorkdays = $this->countWeekdays($yearStart, $yearEnd);
        $sort = in_array($request->query('sort'), self::HOURS_SORTABLE_COLUMNS, true)
            ? $request->query('sort')
            : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        // Ralf, 2026-09-29: Abteilungsfilter funktionierte nicht zuverlässig für
        // alle Szenarien (ohne Mandantenfähigkeit sind alle Personen eigene
        // Mitarbeiter, kein Kriterium zum Ausschließen externer Kräfte) -
        // ersetzt durch eine direkte, manuell gepflegte Markierung je Person
        // (Person::resource_planning, siehe Personendetails).
        $people = (clone $peopleQuery)
            ->where('resource_planning', true)
            ->with(['weeklyHours', 'vacationDays', 'department'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $collator = new \Collator('de_DE');
        $rows = $people->map(function (Person $person) use ($yearStart, $yearEnd, $totalWorkdays) {
            $brutto = $this->overlappingSum($person->weeklyHours, $yearStart, $yearEnd, fn (PersonWeeklyHours $row, int $segmentWorkdays) => $segmentWorkdays * ((float) $row->hours / 5));
            if ($brutto === null) {
                return null;
            }

            $effectiveWoSt = $totalWorkdays > 0 ? $brutto / $totalWorkdays * 5 : 0.0;

            // Urlaubstage-Historie (Ralf, 2026-09-28): ändert sich der
            // Jahresanspruch innerhalb des Jahres, wird er anteilig nach
            // Arbeitstagen der jeweiligen Gültigkeit geblendet (analog zu
            // einer unterjährigen Anpassung) - dieselbe Denkweise wie bei
            // den Wochenstunden.
            $vacationDaysEffective = $this->overlappingSum($person->vacationDays, $yearStart, $yearEnd, fn (PersonVacationDays $row, int $segmentWorkdays) => $totalWorkdays > 0 ? (float) $row->days * $segmentWorkdays / $totalWorkdays : 0.0) ?? 0.0;
            $vacationHours = $vacationDaysEffective * $effectiveWoSt / 5;
            $netto = $brutto - $vacationHours;

            return [
                'personId' => $person->id,
                'firstName' => $person->first_name,
                'lastName' => $person->last_name,
                'sortKey' => $person->last_name.', '.$person->first_name,
                'department' => $person->department?->name ?? '',
                'wost' => $effectiveWoSt,
                'vacationHours' => $vacationHours,
                'jahresstd' => $netto,
            ];
        })
            ->filter()
            ->sort(function ($a, $b) use ($collator, $direction, $sort, $totalWorkdays) {
                $result = match ($sort) {
                    'department' => $collator->compare($a['department'], $b['department']),
                    'wost' => $a['wost'] <=> $b['wost'],
                    'workdays' => $totalWorkdays <=> $totalWorkdays,
                    'vacation_hours' => $a['vacationHours'] <=> $b['vacationHours'],
                    'annual_hours' => $a['jahresstd'] <=> $b['jahresstd'],
                    default => $collator->compare($a['sortKey'], $b['sortKey']),
                };
                if ($result === 0 && $sort !== 'name') {
                    $result = $collator->compare($a['sortKey'], $b['sortKey']);
                }

                return $direction === 'desc' ? -$result : $result;
            })
            ->values();

        $total = (float) $rows->sum('jahresstd');

        return view('planning.stunden', compact('years', 'year', 'totalWorkdays', 'rows', 'total', 'sort', 'direction'));
    }

    private function planningYears($personIds, Request $request): array
    {
        $dateValues = DB::table('person_weekly_hours')->whereIn('person_id', $personIds)
            ->get(['valid_from', 'valid_to'])
            ->flatMap(fn ($row) => [$row->valid_from, $row->valid_to])
            ->filter();
        $currentYear = (int) now()->year;
        $minYear = $dateValues->isNotEmpty() ? (int) $dateValues->map(fn ($date) => substr($date, 0, 4))->min() : $currentYear;
        $maxYear = max($currentYear, $dateValues->isNotEmpty() ? (int) $dateValues->map(fn ($date) => substr($date, 0, 4))->max() : $currentYear);
        $years = range($maxYear, $minYear, -1);
        $year = (int) $request->integer('year', $currentYear);

        return [$years, in_array($year, $years, true) ? $year : $years[0]];
    }

    private function overlappingWeeklyHours(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd)
    {
        return $person->weeklyHours->filter(function (PersonWeeklyHours $row) use ($yearStart, $yearEnd) {
            $rowStart = $row->valid_from ? CarbonImmutable::parse($row->valid_from->toDateString()) : $yearStart;
            $rowEnd = $row->valid_to ? CarbonImmutable::parse($row->valid_to->toDateString()) : $yearEnd;

            return $rowStart->lessThanOrEqualTo($yearEnd) && $rowEnd->greaterThanOrEqualTo($yearStart);
        })->values();
    }

    /**
     * Summiert $valueFn über alle Historien-Zeilen, die das Jahr
     * [$yearStart, $yearEnd] überhaupt berühren - je Zeile begrenzt auf den
     * Überschneidungs-Zeitraum mit dem Jahr (Ralf, 2026-09-28: eine Person
     * kann ihre Wochenstunden/ihren Urlaubsanspruch unterjährig ändern, die
     * Jahresstunden dürfen dadurch nicht falsch werden). Gibt null zurück,
     * wenn KEINE Zeile das Jahr berührt (Person hat dafür noch keine gültigen
     * Daten - wird dann aus der Liste ausgeschlossen).
     *
     * @param  Collection<int, PersonWeeklyHours|PersonVacationDays>  $history
     * @param  \Closure(PersonWeeklyHours|PersonVacationDays, int): float  $valueFn
     */
    private function overlappingSum($history, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, \Closure $valueFn): ?float
    {
        $touched = false;
        $sum = 0.0;
        foreach ($history as $row) {
            $rowStart = $row->valid_from ? CarbonImmutable::parse($row->valid_from->toDateString()) : $yearStart;
            $rowEnd = $row->valid_to ? CarbonImmutable::parse($row->valid_to->toDateString()) : $yearEnd;
            $segmentStart = $rowStart->greaterThan($yearStart) ? $rowStart : $yearStart;
            $segmentEnd = $rowEnd->lessThan($yearEnd) ? $rowEnd : $yearEnd;
            if ($segmentStart->greaterThan($segmentEnd)) {
                continue;
            }
            $touched = true;
            $sum += $valueFn($row, $this->countWeekdays($segmentStart, $segmentEnd));
        }

        return $touched ? $sum : null;
    }

    /**
     * Reine Wochentage (Mo-Fr) im Zeitraum, ohne Feiertage oder
     * Krankheitstage abzuziehen (Ralf, 2026-09-28, bewusste Vereinfachung).
     */
    private function countWeekdays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($start->greaterThan($end)) {
            return 0;
        }
        $days = $start->diffInDays($end) + 1;
        $fullWeeks = intdiv($days, 7);
        $count = $fullWeeks * 5;
        $cursor = $start->addWeeks($fullWeeks);
        for ($i = 0; $i < $days % 7; $i++) {
            if ($cursor->isWeekday()) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
