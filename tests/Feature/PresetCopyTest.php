<?php

namespace Tests\Feature;

use App\Models\Market;
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
}
