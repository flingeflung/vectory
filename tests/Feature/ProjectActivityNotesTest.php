<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vorgänge von Hand erfassen, ändern, löschen und hervorheben (Ralf, 2026-10-09, Vorbild Vietto). */
class ProjectActivityNotesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->project = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '260901', 'title' => 'Vorgangstest', 'status' => 1]);
    }

    private function admin(): User
    {
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
    }

    private function userWith(array $keys): User
    {
        $set = PermissionTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Set '.random_int(1000, 9999)]);
        $set->permissions()->attach(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'P'.random_int(1000, 9999), 'active' => true, 'permission_template_id' => $set->id]);

        return User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'role' => 'user']);
    }

    private function note(User $author, string $text = 'Telefonat mit Druckerei', string $date = '2026-10-03'): Activity
    {
        return Activity::create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'user_id' => $author->id, 'type' => ActivityType::Note, 'message' => $text, 'is_automatic' => false, 'occurred_on' => $date]);
    }

    public function test_a_note_can_be_created_with_its_own_date_and_highlight(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)->postJson(route('projekte.vorgaenge.store', $this->project), ['message' => '  Freigabe telefonisch erteilt ', 'occurred_on' => '2026-10-03', 'is_highlighted' => true])
            ->assertOk()->json('html');

        $activity = Activity::query()->where('project_id', $this->project->id)->firstOrFail();
        $this->assertSame(ActivityType::Note, $activity->type);
        $this->assertFalse($activity->is_automatic);
        $this->assertSame('Freigabe telefonisch erteilt', $activity->message);
        $this->assertSame('2026-10-03', $activity->occurred_on->format('Y-m-d'));
        $this->assertTrue($activity->is_highlighted);
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertStringContainsString('03.10.2026', $html);
        $this->assertStringContainsString('bg-yellow-100', $html);
    }

    public function test_input_is_validated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('projekte.vorgaenge.store', $this->project), ['message' => '', 'occurred_on' => '2026-10-03'])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('projekte.vorgaenge.store', $this->project), ['message' => 'x', 'occurred_on' => '03.10.2026'])->assertUnprocessable();
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_the_automatic_log_never_highlights_by_itself_but_anything_can_be_highlighted_by_hand(): void
    {
        $admin = $this->admin();
        $auto = Activity::log($this->project, ActivityType::StatusChanged, 'Status geändert.');
        $this->assertFalse($auto->fresh()->is_highlighted);

        $this->actingAs($admin)->postJson(route('projekte.vorgaenge.highlight', [$this->project, $auto]), ['highlighted' => true])->assertOk();
        $this->assertTrue($auto->fresh()->is_highlighted);
        $this->actingAs($admin)->postJson(route('projekte.vorgaenge.highlight', [$this->project, $auto]), ['highlighted' => false])->assertOk();
        $this->assertFalse($auto->fresh()->is_highlighted);
    }

    public function test_users_change_only_own_notes_admins_any_note_and_nobody_automatic_entries(): void
    {
        $author = $this->userWith(['project.view', 'project.edit']);
        $other = $this->userWith(['project.view', 'project.edit']);
        $admin = $this->admin();
        $note = $this->note($author);
        $auto = Activity::log($this->project, ActivityType::StatusChanged, 'Status geändert.');
        $payload = ['message' => 'Geändert', 'occurred_on' => '2026-10-04', 'is_highlighted' => false];

        $this->actingAs($other)->putJson(route('projekte.vorgaenge.update', [$this->project, $note]), $payload)->assertForbidden();
        $this->actingAs($other)->deleteJson(route('projekte.vorgaenge.destroy', [$this->project, $note]))->assertForbidden();

        $this->actingAs($author)->putJson(route('projekte.vorgaenge.update', [$this->project, $note]), $payload)->assertOk();
        $note->refresh();
        $this->assertSame('Geändert', $note->message);
        $this->assertSame($author->id, $note->edited_by);
        $this->assertNotNull($note->edited_at);

        $this->actingAs($admin)->putJson(route('projekte.vorgaenge.update', [$this->project, $note]), ['message' => 'Admin war hier'] + $payload)->assertOk();
        $this->assertSame('Admin war hier', $note->fresh()->message);

        // Automatische Vorgänge bleiben unverändert, auch für Administratoren
        $this->actingAs($admin)->putJson(route('projekte.vorgaenge.update', [$this->project, $auto]), $payload)->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('projekte.vorgaenge.destroy', [$this->project, $auto]))->assertForbidden();
        $this->assertModelExists($auto);

        $this->actingAs($author)->deleteJson(route('projekte.vorgaenge.destroy', [$this->project, $note]))->assertOk();
        $this->assertModelMissing($note);
    }

    public function test_without_the_edit_right_nothing_can_be_written(): void
    {
        $viewer = $this->userWith(['project.view']);
        $note = $this->note($this->admin());

        $this->actingAs($viewer)->postJson(route('projekte.vorgaenge.store', $this->project), ['message' => 'x', 'occurred_on' => '2026-10-03'])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('projekte.vorgaenge.highlight', [$this->project, $note]), ['highlighted' => true])->assertForbidden();
        $this->assertStringNotContainsString('Neuer Vorgang', $this->actingAs($viewer)->getJson(route('projekte.vorgaenge', $this->project))->assertOk()->json('html'));
    }

    public function test_the_order_is_by_event_date_and_each_person_chooses_newest_or_oldest_first(): void
    {
        $admin = $this->admin();
        $this->note($admin, 'ZWEITER', '2026-10-05');
        $this->note($admin, 'ERSTER', '2026-10-01');
        $this->note($admin, 'DRITTER', '2026-10-09');

        $html = $this->actingAs($admin)->getJson(route('projekte.vorgaenge', $this->project))->json('html');
        $this->assertLessThan(strpos($html, 'ZWEITER'), strpos($html, 'DRITTER'));
        $this->assertLessThan(strpos($html, 'ERSTER'), strpos($html, 'ZWEITER'));

        $html = $this->actingAs($admin)->postJson(route('projekte.vorgaenge.order', $this->project), ['newest_first' => false])->json('html');
        $this->assertLessThan(strpos($html, 'ZWEITER'), strpos($html, 'ERSTER'));
        $this->assertFalse(UserPreference::configFor($admin->id, UserPreference::PROJECT_ACTIVITIES)['newest_first']);

        // Eine andere Person behält ihre eigene Einstellung
        $html = $this->actingAs($this->admin())->getJson(route('projekte.vorgaenge', $this->project))->json('html');
        $this->assertLessThan(strpos($html, 'ERSTER'), strpos($html, 'DRITTER'));
    }

    public function test_the_block_renders_with_an_intact_alpine_component_and_the_details_page_includes_it(): void
    {
        $admin = $this->admin();
        $this->note($admin, 'Text mit "Anführungszeichen" und Zeilenumbruch');

        $html = $this->actingAs($admin)->getJson(route('projekte.vorgaenge', $this->project))->json('html');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $found = 0;
        foreach ((new \DOMXPath($dom))->query('//*[@x-data]') as $node) {
            if (str_contains($node->getAttribute('x-data'), 'editing: false')) {
                $found++;
                $this->assertStringContainsString('Anführungszeichen', $node->getAttribute('x-data'));
            }
        }
        $this->assertSame(1, $found);

        $this->actingAs($admin)->get(route('projekte.show', $this->project))->assertOk()->assertSee('project-activities-'.$this->project->id);
    }
}
