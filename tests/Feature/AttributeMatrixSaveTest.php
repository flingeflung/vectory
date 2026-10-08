<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Raster Projektart x Attribut speichert gemeinsam über "Speichern" (Ralf, 2026-10-08). */
class AttributeMatrixSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_saves_assignments_and_the_all_switch_together(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $main = ProjectTypeMain::query()->create(['tenant_id' => $tenant->id, 'name' => 'Print', 'active' => true, 'sort' => 1]);
        $subs = collect(['A', 'B', 'C'])->map(fn ($name, $i) => ProjectTypeSub::query()->create([
            'tenant_id' => $tenant->id, 'project_type_main_id' => $main->id, 'name' => $name, 'active' => true, 'sort' => $i + 1,
        ]));
        $make = fn (string $key, string $section) => Attribute::query()->create([
            'tenant_id' => $tenant->id, 'section' => $section, 'system' => false, 'key' => $key, 'label' => $key, 'data_type' => 'text', 'sort' => 1,
        ]);
        $typeSpecific = $make('feld_typ', 'typspezifisch');
        $base = $make('feld_stamm', 'stammdaten');
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);

        // Typspezifisch: nur A und C
        $this->actingAs($admin)->post(route('admin.projektattribute.matrix.save'), [
            'section' => 'typspezifisch', 'attributes' => [$typeSpecific->id],
            'assign' => [$typeSpecific->id => [$subs[0]->id, $subs[2]->id]],
        ])->assertRedirect();
        $this->assertSame([$subs[0]->id, $subs[2]->id], DB::table('attribute_project_type')->where('attribute_id', $typeSpecific->id)->orderBy('project_type_sub_id')->pluck('project_type_sub_id')->map(fn ($id) => (int) $id)->all());

        // Stammdaten: "alle" aus, nur B angehakt -> Geltung auf B beschränkt
        $this->actingAs($admin)->post(route('admin.projektattribute.matrix.save'), [
            'section' => 'stammdaten', 'attributes' => [$base->id], 'assign' => [$base->id => [$subs[1]->id]],
        ])->assertRedirect();
        $this->assertFalse((bool) $base->fresh()->applies_to_all_types);
        $this->assertSame([$subs[1]->id], DB::table('attribute_project_type')->where('attribute_id', $base->id)->pluck('project_type_sub_id')->map(fn ($id) => (int) $id)->all());

        // wieder "alle": Flag gesetzt, Zuordnungen bleiben unverändert
        $this->actingAs($admin)->post(route('admin.projektattribute.matrix.save'), [
            'section' => 'stammdaten', 'attributes' => [$base->id], 'all' => [$base->id],
        ])->assertRedirect();
        $this->assertTrue((bool) $base->fresh()->applies_to_all_types);
        $this->assertSame(1, DB::table('attribute_project_type')->where('attribute_id', $base->id)->count());

        // das Raster einer anderen Sektion darf Attribute fremder Sektionen nicht anfassen
        $this->actingAs($admin)->post(route('admin.projektattribute.matrix.save'), [
            'section' => 'ablaufdaten', 'attributes' => [$typeSpecific->id], 'assign' => [$typeSpecific->id => []],
        ])->assertRedirect();
        $this->assertSame(2, DB::table('attribute_project_type')->where('attribute_id', $typeSpecific->id)->count());

        // die Seite zeigt das Raster mit Speichern-Knopf und ohne Sofort-Hinweis
        $this->actingAs($admin)->get(route('admin.projektattribute', ['bereich' => 'typspezifisch']))->assertOk()->assertSee('Änderungen gelten erst nach „Speichern“');
    }
}
