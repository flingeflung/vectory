<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /** @param list<string> $isos */
    private function group(Tenant $tenant, string $name, array $isos): \App\Models\MarketSet
    {
        $set = \App\Models\MarketSet::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'name' => $name, 'sort' => 1]);
        foreach ($isos as $iso) {
            $market = $this->market($tenant, $iso, strtolower($iso), $iso.'-Land');
            $set->markets()->attach($market->id, ['tenant_id' => $tenant->id]);
        }

        return $set;
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
        $this->apply(['market-sets' => ['g:1' => '1']])->assertRedirect();
        $this->assertSame(0, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_page_lists_country_groups_with_conflicts(): void
    {
        $this->group($this->source, 'DACH', ['DE']);
        $this->group($this->target, 'DACH', ['DE']);
        $this->group($this->source, 'Nordics', ['SE']);

        $this->get(route('admin.voreinstellungen', ['source' => $this->source->id, 'target' => $this->target->id]))
            ->assertOk()->assertSee('Nordics')->assertSee('gibt es schon');
    }

    public function test_existing_country_group_is_skipped_or_overwritten_with_the_source_members(): void
    {
        $source = $this->group($this->source, 'DACH', ['DE', 'AT']);
        $existing = $this->group($this->target, 'DACH', ['CH']);

        $this->apply(['market-sets' => ['g:'.$source->id => '1']]);
        $this->assertSame(['CH'], $existing->markets()->withoutGlobalScope('tenant')->pluck('country_iso')->all(), 'Standard bei Gleichnamigem ist Überspringen.');

        $this->apply(['market-sets' => ['g:'.$source->id => '1']], ['market-sets' => ['g:'.$source->id => 'overwrite']]);
        $this->assertEqualsCanonicalizing(['DE', 'AT'], $existing->markets()->withoutGlobalScope('tenant')->pluck('country_iso')->all());
        $this->assertSame(1, \App\Models\MarketSet::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
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
        $this->group($this->source, 'DACH', ['DE']);

        $this->apply([])->assertSessionHas('notice');
        $this->assertSame(0, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_deactivated_organizations_cannot_be_used(): void
    {
        $this->target->update(['is_active' => false]);

        $this->apply(['market-sets' => ['g:1' => '1']])->assertStatus(422);
    }

    public function test_country_group_brings_its_markets_along(): void
    {
        $de = $this->market($this->source, 'DE', 'de', 'Deutschland');
        $fr = $this->market($this->source, 'FR', 'fr', 'Frankreich');
        $set = \App\Models\MarketSet::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'DACH', 'sort' => 1]);
        $set->markets()->sync([$de->id => ['tenant_id' => $this->source->id], $fr->id => ['tenant_id' => $this->source->id]]);

        $this->apply(['market-sets' => ['g:'.$set->id => '1']]);

        $targetSet = \App\Models\MarketSet::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame('DACH', $targetSet->name);
        $this->assertEqualsCanonicalizing(['DE', 'FR'], $targetSet->markets()->withoutGlobalScope('tenant')->pluck('country_iso')->all());
        $this->assertSame(2, Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());

        // Zweites Mal: Gleichnamiges wird übersprungen, nichts doppelt.
        $this->apply(['market-sets' => ['g:'.$set->id => '1']]);
        $this->assertSame(1, \App\Models\MarketSet::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    private function attribute(Tenant $tenant, string $key, array $extra = []): \App\Models\Attribute
    {
        $attribute = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->create($extra + [
            'tenant_id' => $tenant->id, 'section' => 'stammdaten', 'key' => $key, 'label' => ucfirst($key),
            'data_type' => \App\Models\Attribute::DATA_TYPE_TEXT, 'sort' => 1,
        ]);
        app(\App\Services\AttributeColumnManager::class)->ensureColumn($attribute);

        return $attribute;
    }

    public function test_attribute_is_copied_with_options_and_project_type_assignment(): void
    {
        $main = $this->main($this->source, 'Dokumente');
        $sub = $this->sub($this->source, $main, 'Handbuch');
        $targetMain = $this->main($this->target, 'Dokumente');
        $targetSub = $this->sub($this->target, $targetMain, 'Handbuch');
        $attr = $this->attribute($this->source, 'farbigkeit_test', [
            'data_type' => \App\Models\Attribute::DATA_TYPE_SELECT, 'applies_to_all_types' => false, 'unit' => 'mm', 'number_decimals' => 2,
        ]);
        \App\Models\AttributeOption::query()->create(['attribute_id' => $attr->id, 'value' => '4c', 'label' => '4-farbig', 'sort' => 1]);
        DB::table('attribute_project_type')->insert(['attribute_id' => $attr->id, 'project_type_sub_id' => $sub->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->apply(['attributes' => ['a:farbigkeit_test' => '1']])->assertRedirect();

        $copy = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->where('key', 'farbigkeit_test')->firstOrFail();
        $this->assertSame('mm', $copy->unit);
        $this->assertSame(['4c'], $copy->options->pluck('value')->all());
        $this->assertSame([$targetSub->id], DB::table('attribute_project_type')->where('attribute_id', $copy->id)->pluck('project_type_sub_id')->all());
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('projects', 'attributes_farbigkeit_test'));
        \Illuminate\Support\Facades\Schema::table('projects', fn ($t) => $t->dropColumn('attributes_farbigkeit_test'));
    }

    public function test_same_key_can_be_renamed_and_overwrite_refuses_a_different_field_type(): void
    {
        $this->attribute($this->source, 'gleich_test', ['label' => 'Neu', 'data_type' => \App\Models\Attribute::DATA_TYPE_NUMBER]);
        $existing = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->target->id, 'section' => 'stammdaten', 'key' => 'gleich_test', 'label' => 'Alt',
            'data_type' => \App\Models\Attribute::DATA_TYPE_TEXT, 'sort' => 1,
        ]);

        $this->apply(['attributes' => ['a:gleich_test' => '1']], ['attributes' => ['a:gleich_test' => 'overwrite']]);
        $this->assertSame('Alt', $existing->fresh()->label, 'Anderer Feldtyp: Überschreiben darf nichts ändern.');

        $this->apply(['attributes' => ['a:gleich_test' => '1']], ['attributes' => ['a:gleich_test' => 'rename']]);
        $keys = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->orderBy('id')->pluck('key')->all();
        $this->assertSame(['gleich_test', 'gleich_test_kopie'], $keys);

        \Illuminate\Support\Facades\Schema::table('projects', fn ($t) => $t->dropColumn(['attributes_gleich_test', 'attributes_gleich_test_kopie', 'attributes_gleich_test_sort', 'attributes_gleich_test_kopie_sort']));
    }

    public function test_system_field_applicability_and_editable_label_are_overwritten(): void
    {
        app(\App\Services\TenantConfigCloner::class)->seedSystemAttributes($this->source);
        app(\App\Services\TenantConfigCloner::class)->seedSystemAttributes($this->target);
        $sourceModel = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->source->id)->where('key', 'system_model')->firstOrFail();
        $sourceModel->update(['label' => 'Modelle', 'applies_to_all_types' => false]);
        $targetModel = \App\Models\Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->where('key', 'system_model')->firstOrFail();
        $this->assertSame('Produkt/Modell/System/Typ', $targetModel->label, 'Neue Vorbelegung der Bezeichnung.');

        $this->apply(['attributes' => ['s:system_model' => '1']], ['attributes' => ['s:system_model' => 'overwrite']]);

        $this->assertSame('Modelle', $targetModel->fresh()->label);
        $this->assertFalse((bool) $targetModel->fresh()->applies_to_all_types);
    }

    private function workflow(Tenant $tenant, string $name, int $steps = 2, bool $published = false): \App\Models\Workflow
    {
        $workflow = \App\Models\Workflow::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'short_name' => strtoupper(substr($name, 0, 3)), 'name' => $name, 'active' => true, 'sort' => 1,
            'published_at' => $published ? now() : null,
        ]);
        for ($i = 1; $i <= $steps; $i++) {
            \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id, 'workflow_id' => $workflow->id, 'title' => 'Schritt '.$i, 'sort' => $i,
            ]);
        }

        return $workflow;
    }

    public function test_workflow_is_copied_with_steps_and_overwrite_is_blocked_when_published_or_used(): void
    {
        $source = $this->workflow($this->source, 'Print', 3);

        $this->apply(['workflows' => ['w:'.$source->id => '1']]);
        $copy = \App\Models\Workflow::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame(3, \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $copy->id)->count());

        // Entwurf, unbenutzt: Überschreiben ersetzt die Schritte komplett.
        \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $source->id)->orderBy('sort')->first()->update(['title' => 'Geändert']);
        $this->apply(['workflows' => ['w:'.$source->id => '1']], ['workflows' => ['w:'.$source->id => 'overwrite']]);
        $this->assertSame(3, \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $copy->id)->count());
        $this->assertContains('Geändert', \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $copy->id)->pluck('title')->all());

        // Verwendet: Überschreiben wird verweigert, Rest bleibt unverändert.
        Project::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->target->id, 'source_pn' => '1', 'title' => 'P', 'status' => 0, 'workflow_id' => $copy->id]);
        \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $source->id)->orderBy('sort')->first()->update(['title' => 'Nochmal anders']);
        $this->apply(['workflows' => ['w:'.$source->id => '1']], ['workflows' => ['w:'.$source->id => 'overwrite']]);
        $this->assertNotContains('Nochmal anders', \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $copy->id)->pluck('title')->all());

        // Umbenennen geht trotzdem.
        $this->apply(['workflows' => ['w:'.$source->id => '1']], ['workflows' => ['w:'.$source->id => 'rename']]);
        $this->assertSame(['Print', 'Print (Kopie)'], \App\Models\Workflow::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->orderBy('id')->pluck('name')->all());
    }

    public function test_published_workflow_in_target_cannot_be_overwritten(): void
    {
        $source = $this->workflow($this->source, 'Print', 2);
        $target = $this->workflow($this->target, 'Print', 1, published: true);

        $this->apply(['workflows' => ['w:'.$source->id => '1']], ['workflows' => ['w:'.$source->id => 'overwrite']]);

        $this->assertSame(1, \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $target->id)->count());
    }

    public function test_template_brings_its_workflow_along(): void
    {
        $workflow = $this->workflow($this->source, 'Print', 2);
        $template = ProjectTemplate::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->source->id, 'name' => 'Fachbuch', 'workflow_id' => $workflow->id, 'format' => 1, 'active' => true, 'duration_value' => 1, 'duration_unit' => 'weeks',
        ]);

        $this->apply(['project-templates' => ['t:'.$template->id => '1']]);

        $copy = ProjectTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame('Fachbuch', $copy->name);
        $this->assertSame('Print', \App\Models\Workflow::query()->withoutGlobalScope('tenant')->findOrFail($copy->workflow_id)->name);
        $this->assertSame($this->target->id, (int) \App\Models\Workflow::query()->withoutGlobalScope('tenant')->findOrFail($copy->workflow_id)->tenant_id);
    }

    public function test_checklist_is_copied_and_overwrite_is_blocked_when_used(): void
    {
        $source = \App\Models\Checklist::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Freigabe', 'active' => true, 'sort' => 1]);
        $section = \App\Models\ChecklistSection::query()->create(['checklist_id' => $source->id, 'title' => 'Inhalt', 'sort' => 1]);
        \App\Models\ChecklistPoint::query()->create(['checklist_section_id' => $section->id, 'title' => 'Text geprüft', 'sort' => 1]);

        $this->apply(['checklists' => ['c:'.$source->id => '1']]);

        $copy = \App\Models\Checklist::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame(1, $copy->load('sections.points')->pointsCount());

        $project = Project::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->target->id, 'source_pn' => '2', 'title' => 'P', 'status' => 0]);
        \App\Models\ProjectChecklist::query()->withoutGlobalScopes()->create(['tenant_id' => $this->target->id, 'project_id' => $project->id, 'checklist_id' => $copy->id]);
        \App\Models\ChecklistPoint::query()->where('checklist_section_id', $section->id)->update(['title' => 'Geändert']);

        $this->apply(['checklists' => ['c:'.$source->id => '1']], ['checklists' => ['c:'.$source->id => 'overwrite']]);
        $this->assertSame('Text geprüft', $copy->fresh()->load('sections.points')->sections->first()->points->first()->title, 'Verwendete Checkliste darf nicht überschrieben werden.');
    }

    public function test_mail_template_and_copy_template_are_copied(): void
    {
        $mail = \App\Models\MailTemplate::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Start', 'subject' => 'Hallo', 'body' => 'Text {title}']);
        $attr = $this->attribute($this->source, 'kopier_test');
        $this->attribute($this->target, 'kopier_test');
        $copyTemplate = \App\Models\CopyTemplate::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Standardkopie', 'sort' => 1]);
        DB::table('copy_template_attribute')->insert(['copy_template_id' => $copyTemplate->id, 'attribute_id' => $attr->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->apply(['mail-templates' => ['m:'.$mail->id => '1'], 'copy-templates' => ['k:'.$copyTemplate->id => '1']]);

        $this->assertSame('Text {title}', \App\Models\MailTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->value('body'));
        $targetCopy = \App\Models\CopyTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->firstOrFail();
        $this->assertSame(1, DB::table('copy_template_attribute')->where('copy_template_id', $targetCopy->id)->count());
        \Illuminate\Support\Facades\Schema::table('projects', fn ($t) => $t->dropColumn('attributes_kopier_test'));
    }

    public function test_holidays_job_types_and_paper_formats_are_copied(): void
    {
        $holiday = \App\Models\Holiday::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->source->id, 'name' => 'Tag der Einheit', 'date' => '2026-10-03', 'weekday' => 6, 'active' => true,
        ]);
        \App\Models\Holiday::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->source->id, 'name' => 'Brückentag', 'date' => '2026-10-02', 'weekday' => 5, 'active' => false,
        ]);
        $groupId = DB::table('job_groups')->insertGetId(['tenant_id' => $this->source->id, 'name' => 'Redaktion', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $jobId = DB::table('job_types')->insertGetId(['tenant_id' => $this->source->id, 'job_group_id' => $groupId, 'code' => 'LEK', 'name' => 'Lektorat', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $a4 = \App\Models\PaperFormat::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'A4', 'width_mm' => 210, 'height_mm' => 297, 'active' => true, 'sort' => 1]);
        $a5 = \App\Models\PaperFormat::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'A5', 'width_mm' => 148, 'height_mm' => 210, 'active' => true, 'sort' => 2]);
        $combo = \App\Models\PaperFormatCombination::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'input_format_id' => $a4->id, 'output_format_id' => $a5->id, 'fold_count' => 1, 'active' => true]);

        $this->apply([
            'holidays' => ['y:2026' => '1'],
            'job-types' => ['j:'.$jobId => '1'],
            'paper-formats' => ['p:'.$combo->id => '1'],
        ])->assertRedirect();

        $this->assertSame(2, \App\Models\Holiday::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
        $this->assertSame(['Redaktion'], DB::table('job_groups')->where('tenant_id', $this->target->id)->pluck('name')->all(), 'Jobgruppe kommt mit dem Jobtyp.');
        $this->assertSame(['LEK'], DB::table('job_types')->where('tenant_id', $this->target->id)->pluck('code')->all());
        $this->assertSame(2, \App\Models\PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count(), 'Kombination bringt beide Formate mit.');
        $this->assertSame(1, \App\Models\PaperFormatCombination::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());

        // Inaktiv-Status wandert mit.
        $this->assertFalse((bool) \App\Models\Holiday::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->where('name', 'Brückentag')->value('active'));

        // Zweiter Durchgang: nichts doppelt.
        $this->apply([
            'holidays' => ['y:2026' => '1'], 'job-types' => ['j:'.$jobId => '1'], 'paper-formats' => ['p:'.$combo->id => '1'],
        ]);
        $this->assertSame(2, \App\Models\Holiday::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
        $this->assertSame(1, DB::table('job_types')->where('tenant_id', $this->target->id)->count());
        $this->assertSame(2, \App\Models\PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->count());
    }

    public function test_master_data_catalogs_are_copied_renamed_and_overwritten(): void
    {
        $dept = \App\Models\Department::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Redaktion', 'short_name' => 'RED', 'active' => true, 'sort' => 1]);
        \App\Models\Department::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->target->id, 'name' => 'Redaktion', 'short_name' => 'alt', 'active' => true, 'sort' => 1]);
        $company = \App\Models\Company::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Übersetzer GmbH', 'short_name' => 'UEB', 'sort' => 1]);
        $role = \App\Models\LegacyRole::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Lektor', 'sort' => 1]);
        $unit = \App\Models\BusinessUnit::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->source->id, 'name' => 'Hausgeräte', 'active' => true, 'sort' => 1]);

        $this->apply([
            'companies' => ['r:'.$company->id => '1'], 'legacy-roles' => ['r:'.$role->id => '1'],
            'business-units' => ['r:'.$unit->id => '1'], 'departments' => ['r:'.$dept->id => '1'],
        ], ['departments' => ['r:'.$dept->id => 'rename']])->assertRedirect();

        $names = fn (string $model) => $model::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['Übersetzer GmbH'], $names(\App\Models\Company::class));
        $this->assertSame(['Lektor'], $names(\App\Models\LegacyRole::class));
        $this->assertSame(['Hausgeräte'], $names(\App\Models\BusinessUnit::class));
        $this->assertSame(['Redaktion', 'Redaktion (Kopie)'], $names(\App\Models\Department::class));

        $this->apply(['departments' => ['r:'.$dept->id => '1']], ['departments' => ['r:'.$dept->id => 'overwrite']]);
        $this->assertSame('RED', \App\Models\Department::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->where('name', 'Redaktion')->value('short_name'));
    }

    public function test_permission_sets_are_never_overwritten_and_bring_basis_and_building_blocks(): void
    {
        $permission = \App\Models\Permission::query()->firstOrFail();
        $make = fn (Tenant $tenant, string $name, bool $baustein = false, ?int $basis = null) => \App\Models\PermissionTemplate::query()->withoutGlobalScope('tenant')
            ->create(['tenant_id' => $tenant->id, 'name' => $name, 'is_baustein' => $baustein, 'basis_id' => $basis, 'sort' => 1]);
        $tr = $make($this->source, 'TR');
        $tr->permissions()->sync([$permission->id]);
        $block = $make($this->source, 'Projektleitung', true);
        $set = $make($this->source, 'TR + PL', false, $tr->id);
        $set->bausteine()->sync([$block->id]);

        $this->apply(['permission-templates' => ['p:'.$set->id => '1']]);

        $names = fn () => \App\Models\PermissionTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->orderBy('id')->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['TR + PL', 'TR', 'Projektleitung'], $names(), 'Basis und Baustein kommen als Voraussetzung mit.');
        $copy = \App\Models\PermissionTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->target->id)->where('name', 'TR + PL')->firstOrFail();
        $this->assertSame('TR', $copy->basis->name);
        $this->assertSame($this->target->id, (int) $copy->basis->tenant_id);
        $this->assertSame(1, $copy->bausteine()->count());
        $this->assertTrue($copy->hasPermission($permission->key), 'Rechte der Basis wirken.');

        // Zweites Mal: nichts doppelt, auch "Überschreiben" überschreibt nicht.
        $this->apply(['permission-templates' => ['p:'.$set->id => '1']], ['permission-templates' => ['p:'.$set->id => 'overwrite']]);
        $this->assertCount(3, $names());

        // Umbenennen legt eine Kopie an.
        $this->apply(['permission-templates' => ['p:'.$set->id => '1']], ['permission-templates' => ['p:'.$set->id => 'rename']]);
        $this->assertContains('TR + PL (Kopie)', $names());
    }

    public function test_source_and_target_are_remembered_per_user(): void
    {
        $this->get(route('admin.voreinstellungen', ['source' => $this->source->id, 'target' => $this->target->id]))->assertOk();

        $this->get(route('admin.voreinstellungen'))->assertOk()
            ->assertSee('value="'.$this->source->id.'" selected', false)
            ->assertSee('value="'.$this->target->id.'" selected', false);
    }
}
