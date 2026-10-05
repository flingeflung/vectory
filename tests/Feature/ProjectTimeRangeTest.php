<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Zeiten › Zeitverlauf: die Zeitachse reicht vom frühesten bis zum spätesten Datum aus Projektdaten UND Buchungen (Ralf, 2026-10-05).
 */
class ProjectTimeRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_outside_the_project_dates_extend_the_time_axis(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $this->actingAs($user);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id, 'source_pn' => '290050', 'title' => 'Zeitachse', 'status' => 0,
            'start_date' => '2026-09-07', 'end_date' => '2026-09-18',
        ]);
        $person = Person::query()->create(['tenant_id' => $tenant->id, 'last_name' => 'Bucher', 'short_name' => 'BU', 'active' => true]);
        $jobType = DB::table('job_types')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Test', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['2026-08-20', '2026-10-05'] as $day) {
            DB::table('job_hours')->insert([
                'tenant_id' => $tenant->id, 'person_id' => $person->id, 'job_type_id' => $jobType, 'project_id' => $project->id,
                'work_date' => $day, 'hours' => 1.5, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $html = $this->get(route('projekte.zeiten.gesamt', $project))->assertOk()->getContent();

        $this->assertStringContainsString('KW 34', $html); // 20.08.2026 liegt vor dem Projektstart
        $this->assertStringContainsString('KW 41', $html); // 05.10.2026 liegt nach dem Projektende
        $this->assertStringNotContainsString('KW 33', $html);
        $this->assertStringNotContainsString('KW 42', $html);
    }
}
