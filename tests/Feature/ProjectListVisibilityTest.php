<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sichtbarkeit und Öffnen von Projekten (Ralf, 2026-10-08): Projektbeteiligte sehen/öffnen ihr Projekt immer; fremde Projekte
 * erscheinen nur mit Recht oder wenn die Organisation es erlaubt - öffnen geht dort nur mit Recht oder Admin-Stufe.
 */
class ProjectListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Project $own;

    private Project $foreign;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->own = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '280001', 'title' => 'Eigenes', 'status' => 0, 'start_date' => '2027-03-01']);
        $this->foreign = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '280002', 'title' => 'Fremdes', 'status' => 0, 'start_date' => '2027-03-01']);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Beteiligt', 'active' => true]);
        $group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'TR', 'short_name' => 'TR', 'sort' => 1, 'active' => true]);
        ProjectPerson::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->own->id, 'function_group_id' => $group->id, 'person_id' => $person->id]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'person_id' => $person->id]);
        $this->assertFalse($this->user->can('project.view'));
    }

    public function test_own_project_is_always_listed_and_openable(): void
    {
        $this->assertFalse((bool) $this->tenant->show_unopenable_projects);
        $this->actingAs($this->user)->get(route('projekte'))->assertOk()->assertSee('280001')->assertDontSee('280002');
        $this->actingAs($this->user)->get(route('projekte.show', $this->own), ['X-Overlay' => '1'])->assertOk();
    }

    public function test_foreign_project_is_listed_with_checkbox_but_not_openable(): void
    {
        $this->tenant->update(['show_unopenable_projects' => true]);
        $this->actingAs($this->user)->get(route('projekte'))->assertOk()->assertSee('280001')->assertSee('280002');
        $this->actingAs($this->user)->get(route('projekte.show', $this->foreign), ['X-Overlay' => '1'])->assertForbidden();
    }

    public function test_admin_sees_and_opens_everything(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin)->get(route('projekte'))->assertOk()->assertSee('280001')->assertSee('280002');
        $this->actingAs($admin)->get(route('projekte.show', $this->foreign), ['X-Overlay' => '1'])->assertOk();
    }
}
