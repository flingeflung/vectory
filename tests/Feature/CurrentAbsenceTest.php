<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\Person;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_absence_and_icon_are_derived_from_calendar_entries(): void
    {
        CarbonImmutable::setTestNow('2026-09-30 12:00:00');
        $tenant = Tenant::query()->firstOrFail();
        $absentPerson = Person::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Heute',
            'last_name' => 'Abwesend',
        ]);
        $futurePerson = Person::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Später',
            'last_name' => 'Abwesend',
        ]);

        CalendarEntry::query()->create([
            'person_id' => $absentPerson->id,
            'type' => CalendarEntry::TYPE_ABSENCE,
            'starts_on' => '2026-09-29',
            'ends_on' => '2026-10-02',
            'note' => 'Testhinweis',
        ]);
        CalendarEntry::query()->create([
            'person_id' => $futurePerson->id,
            'type' => CalendarEntry::TYPE_ABSENCE,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-02',
        ]);

        $this->assertTrue($absentPerson->isCurrentlyAbsent());
        $this->assertFalse($futurePerson->isCurrentlyAbsent());
        $this->blade('<x-absence-icon :person="$person" />', ['person' => $absentPerson])
            ->assertSee('Abwesenheit vom 29.09.2026 bis 02.10.2026 (Testhinweis)');
    }
}
