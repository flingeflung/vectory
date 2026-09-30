<?php

namespace App\Support;

use App\Models\CalendarEntry;
use Illuminate\Support\Collection;

class CurrentAbsenceLookup
{
    /** @var Collection<int, Collection<int, CalendarEntry>>|null */
    private ?Collection $entriesByPerson = null;

    /** @return Collection<int, CalendarEntry> */
    public function forPerson(int $personId): Collection
    {
        if ($this->entriesByPerson === null) {
            $today = now()->toDateString();
            $this->entriesByPerson = CalendarEntry::query()
                ->where('type', CalendarEntry::TYPE_ABSENCE)
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->orderBy('starts_on')
                ->orderBy('ends_on')
                ->get()
                ->groupBy('person_id');
        }

        return $this->entriesByPerson->get($personId, collect());
    }
}
