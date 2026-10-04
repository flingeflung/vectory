<?php

namespace Tests\Feature;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Blättern in den Projektdetails muss genau der Reihenfolge der Übersicht folgen (Ralf, 2026-10-04) - auch bei
 * Verbünden, deren Unterprojekte mit eigenem Startdatum "dazwischen" liegen.
 */
class ProjectPagingOrderTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $pn, ?string $start, ?int $role = null, ?int $mainId = null): Project
    {
        return Project::query()->create([
            'tenant_id' => Tenant::query()->firstOrFail()->id, 'source_pn' => $pn, 'title' => 'P'.$pn, 'status' => 0,
            'start_date' => $start, 'verbund_rolle' => $role, 'hauptprojekt_id' => $mainId,
        ]);
    }

    /** @return list<string> */
    private function walk(Project $from, string $way): array
    {
        $controller = app(ProjectController::class);
        $method = (new ReflectionClass($controller))->getMethod('adjacentProject');
        $method->setAccessible(true);

        $sequence = [$from->source_pn];
        for ($project = $from; ($project = $method->invoke($controller, null, 'desc', [], $project, $way)) !== null;) {
            $sequence[] = $project->source_pn;
            if (count($sequence) > 20) {
                break; // Schutz gegen Schleifen
            }
        }

        return $sequence;
    }

    public function test_paging_follows_the_overview_order_including_verbund_groups(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'organization_admin']));

        $a = $this->project('270010', '2027-09-21');
        $b = $this->project('270011', '2027-09-14');
        $single = $this->project('270012', '2027-09-05');
        $main = $this->project('270001', '2027-08-05', 1);
        // Unterprojekte mit Startdaten, die zwischen den anderen Projekten liegen; PN-Reihenfolge weicht von der Listenreihenfolge ab
        $this->project('270019', '2027-10-01', 2, $main->id);
        $this->project('270006', '2027-09-14', 2, $main->id);
        $this->project('270002', '2027-07-01', 2, $main->id);
        $last = $this->project('270025', '2027-02-02');

        // Reihenfolge der Übersicht: nach Start absteigend, Unterprojekte direkt hinter ihrem Hauptprojekt (selbst nach Start absteigend)
        $expected = ['270010', '270011', '270012', '270001', '270019', '270006', '270002', '270025'];

        $this->assertSame($expected, $this->walk($a, 'next'));
        $this->assertSame(array_reverse($expected), $this->walk($last, 'previous'));
    }
}
