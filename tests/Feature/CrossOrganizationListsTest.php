<?php

namespace Tests\Feature;

use App\Enums\GraphicOrderStatus;
use App\Enums\TaskSource;
use App\Models\GraphicOrder;
use App\Models\Person;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccessLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aufgaben und Illustrationen über mehrere Organisationen hinweg (Ralf, 2026-10-09): Mitarbeiter der
 * Heimat-Organisation sollen nicht ständig wechseln müssen.
 */
class CrossOrganizationListsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: User, 3: Person, 4: Project, 5: Project} */
    private function setUpOrganizations(): array
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $home = Tenant::query()->firstOrFail();
        $other = Tenant::query()->create(['name' => 'Andere Organisation', 'short_name' => 'AND', 'active' => true]);
        $person = Person::query()->create(['tenant_id' => $home->id, 'last_name' => 'Heimat', 'short_name' => 'HE', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $home->id, 'person_id' => $person->id, 'role' => AccessLevel::SUPER_ADMIN]);
        $homeProject = Project::query()->create(['tenant_id' => $home->id, 'source_pn' => '281001', 'title' => 'Heimatprojekt', 'status' => 1]);
        $otherProject = Project::withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'source_pn' => '281002', 'title' => 'Fremdprojekt', 'status' => 1]);

        return [$home, $other, $user, $person, $homeProject, $otherProject];
    }

    public function test_tasks_of_all_allowed_organizations_are_listed_and_can_be_narrowed(): void
    {
        [$home, $other, $user, $person, $homeProject, $otherProject] = $this->setUpOrganizations();
        Task::query()->create(['tenant_id' => $home->id, 'project_id' => $homeProject->id, 'person_id' => $person->id, 'source' => TaskSource::WorkflowStep]);
        Task::withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'project_id' => $otherProject->id, 'person_id' => $person->id, 'source' => TaskSource::WorkflowStep]);

        $this->actingAs($user)->get(route('aufgaben'))
            ->assertOk()
            ->assertSee('281001')
            ->assertSee('281002')
            ->assertSee('Andere Organisation')
            ->assertSee(route('aufgaben', ['open_project' => $otherProject->id]), false);

        $this->get(route('aufgaben', ['aufgabenfilter_submitted' => 1, 'organizations_submitted' => 1, 'organizations' => [$home->id]]))
            ->assertOk()
            ->assertSee('281001')
            ->assertDontSee('281002');

        // Die Auswahl wird gemerkt.
        $this->get(route('aufgaben'))->assertOk()->assertSee('281001')->assertDontSee('281002');
    }

    public function test_task_of_foreign_organization_can_be_hidden_and_project_opened(): void
    {
        [$home, $other, $user, $person, $homeProject, $otherProject] = $this->setUpOrganizations();
        $task = Task::withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'project_id' => $otherProject->id, 'person_id' => $person->id, 'source' => TaskSource::WorkflowStep]);

        $this->actingAs($user)->post(route('aufgaben.visibility', $task->id))->assertNoContent();
        $this->get(route('aufgaben'))->assertOk()->assertDontSee('281002');
        $this->get(route('aufgaben', ['hidden' => 1]))->assertOk()->assertSee('281002');

        $this->get(route('aufgaben', ['open_project' => $otherProject->id]))->assertRedirect(route('aufgaben'));
        $this->assertSame($other->id, \App\Support\CurrentTenant::id());
    }

    public function test_user_without_access_to_foreign_organization_cannot_switch_via_open_link(): void
    {
        [$home, $other, , , , $otherProject] = $this->setUpOrganizations();
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::USER]);

        $this->actingAs($user)->get(route('aufgaben', ['open_project' => $otherProject->id]))->assertRedirect();
        $this->assertSame($home->id, \App\Support\CurrentTenant::id());
        $this->get(route('illustrationen', ['open_orders' => $otherProject->id]))->assertRedirect();
        $this->assertSame($home->id, \App\Support\CurrentTenant::id());
    }

    public function test_illustration_orders_of_all_allowed_organizations_are_listed(): void
    {
        [$home, $other, $user, $person, $homeProject, $otherProject] = $this->setUpOrganizations();
        GraphicOrder::query()->create(['tenant_id' => $home->id, 'project_id' => $homeProject->id, 'graphic_order_status_id' => GraphicOrderStatus::NeuerAuftrag, 'description' => 'Heimatbild', 'image_count' => 1, 'initiated_by_person_id' => $person->id]);
        GraphicOrder::withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'project_id' => $otherProject->id, 'graphic_order_status_id' => GraphicOrderStatus::NeuerAuftrag, 'description' => 'Fremdbild', 'image_count' => 2]);

        $this->actingAs($user)->get(route('illustrationen'))
            ->assertOk()
            ->assertSee('Heimatbild')
            ->assertSee('Fremdbild')
            ->assertSee('Andere Organisation')
            ->assertSee(route('illustrationen', ['open_orders' => $otherProject->id]), false);

        $this->get(route('illustrationen', ['illustrationsfilter_submitted' => 1, 'organizations_submitted' => 1, 'organizations' => [$other->id], 'q' => '281002']))
            ->assertOk()
            ->assertSee('Fremdbild')
            ->assertDontSee('Heimatbild');

        $this->get(route('illustrationen', ['open_orders' => $otherProject->id]))->assertRedirect(route('illustrationen'));
        $this->assertSame($other->id, \App\Support\CurrentTenant::id());
    }

    public function test_single_installation_shows_no_organization_choice(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => AccessLevel::SUPER_ADMIN]);

        $this->actingAs($user)->get(route('aufgaben'))->assertOk()->assertDontSee('organizations_submitted');
        $this->get(route('illustrationen'))->assertOk()->assertDontSee('organizations_submitted');
    }
}
