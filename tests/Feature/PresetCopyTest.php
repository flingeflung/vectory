<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresetCopyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $source;

    private Tenant $target;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::forgetInactiveCache();
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $this->source = Tenant::query()->create(['name' => 'Quelle', 'short_name' => 'Q']);
        $this->target = Tenant::query()->create(['name' => 'Ziel', 'short_name' => 'Z']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $this->actingAs(User::factory()->create(['tenant_id' => $home->id, 'role' => 'central_admin']));
    }

    protected function tearDown(): void
    {
        Tenant::forgetInactiveCache();
        parent::tearDown();
    }

    private function market(Tenant $tenant, string $iso, string $lang, string $country): Market
    {
        return Market::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'country_iso' => $iso, 'country_name' => $country, 'country_short_name' => $iso,
            'language_code' => $lang, 'language_name' => strtoupper($lang), 'no_translation' => false, 'sort' => 0,
        ]);
    }

    private function main(Tenant $tenant, string $name): ProjectTypeMain
    {
        return ProjectTypeMain::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'name' => $name, 'active' => true, 'sort' => 1]);
    }

    private function sub(Tenant $tenant, ProjectTypeMain $main, string $name, string $color = '#111111'): ProjectTypeSub
    {
        return ProjectTypeSub::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'project_type_main_id' => $main->id, 'name' => $name, 'active' => true,
            'format_type' => 1, 'color' => $color, 'sort' => 1,
        ]);
    }

    private function apply(array $sel, array $act = [])
    {
        return $this->post(route('admin.voreinstellungen.apply'), ['source' => $this->source->id, 'target' => $this->target->id, 'sel' => $sel, 'act' => $act]);
    }

    public function test_only_central_admins_reach_the_page(): void
    {
        $this->get(route('admin.voreinstellungen'))->assertOk();

        $this->actingAs(User::factory()->create(['tenant_id' => $this->source->id, 'role' => 'organization_admin']));
        // Verbotene Seiten werden freundlich zurück zur Startseite geleitet (Hinweis statt Fehlerseite).
        $this->get(route('admin.voreinstellungen'))->assertRedirect();
        $this->apply(['markets' => ['FR|fr' => '1']])->assertRedirect();
        $this->assertSame(0, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_page_lists_entries_with_conflicts(): void
    {
        $this->market($this->source, 'DE', 'de', 'Deutschland');
        $this->market($this->target, 'DE', 'de', 'Deutschland');
        $this->market($this->source, 'FR', 'fr', 'Frankreich');

        $this->get(route('admin.voreinstellungen', ['source' => $this->source->id, 'target' => $this->target->id]))
            ->assertOk()->assertSee('Frankreich')->assertSee('gibt es schon');
    }

    public function test_markets_are_copied_and_existing_ones_are_skipped_or_overwritten(): void
    {
        $this->market($this->source, 'FR', 'fr', 'Frankreich');
        $this->market($this->source, 'DE', 'de', 'Deutschland (neu)');
        $existing = $this->market($this->target, 'DE', 'de', 'Deutschland');

        $this->apply(['markets' => ['FR|fr' => '1', 'DE|de' => '1']])->assertRedirect();

        $this->assertSame(2, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
        $this->assertSame('Deutschland', $existing->fresh()->country_name, 'Standard bei Gleichnamigem ist Überspringen.');

        $this->apply(['markets' => ['DE|de' => '1']], ['markets' => ['DE|de' => 'overwrite']]);
        $this->assertSame('Deutschland (neu)', $existing->fresh()->country_name);
        $this->assertSame(2, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_choosing_a_sub_type_brings_its_main_type_along(): void
    {
        $main = $this->main($this->source, 'Dokumente');
        $sub = $this->sub($this->source, $main, 'Handbuch');
        $this->sub($this->source, $main, 'Flyer');

        $this->apply(['project-types' => ['s:'.$sub->id => '1']]);

        $targetMain = ProjectTypeMain::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame('Dokumente', $targetMain->name);
        $subs = ProjectTypeSub::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->get();
        $this->assertSame(['Handbuch'], $subs->pluck('name')->all());
        $this->assertSame($targetMain->id, $subs->first()->project_type_main_id);
    }

    public function test_same_named_sub_type_can_be_renamed_or_overwritten_in_place(): void
    {
        $main = $this->main($this->source, 'Dokumente');
        $sub = $this->sub($this->source, $main, 'Handbuch', '#ff0000');
        $targetMain = $this->main($this->target, 'Dokumente');
        $existing = $this->sub($this->target, $targetMain, 'Handbuch', '#00ff00');

        $this->apply(['project-types' => ['s:'.$sub->id => '1']], ['project-types' => ['s:'.$sub->id => 'rename']]);

        $names = ProjectTypeSub::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['Handbuch', 'Handbuch (Kopie)'], $names);
        $this->assertSame(1, ProjectTypeMain::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count(), 'Vorhandene Hauptart wird wiederverwendet.');

        $this->apply(['project-types' => ['s:'.$sub->id => '1']], ['project-types' => ['s:'.$sub->id => 'overwrite']]);
        $this->assertSame('#ff0000', $existing->fresh()->color);
    }

    public function test_nothing_selected_changes_nothing(): void
    {
        $this->market($this->source, 'FR', 'fr', 'Frankreich');

        $this->apply([])->assertSessionHas('notice');
        $this->assertSame(0, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_deactivated_organizations_cannot_be_used(): void
    {
        $this->target->update(['is_active' => false]);

        $this->apply(['markets' => ['FR|fr' => '1']])->assertStatus(422);
    }
}
