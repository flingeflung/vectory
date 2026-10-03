<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\ProjectTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTemplateCharacteristicsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $tenant = Tenant::query()->firstOrFail();

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
    }

    private function characteristics(int $value): array
    {
        return collect(array_keys(ProjectTemplate::characteristicFields()))->mapWithKeys(fn ($field) => [$field => $value])->all();
    }

    public function test_template_can_be_created_without_characteristics(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->post(route('admin.projektschablonen.store'), [
            'name' => 'Verwaltung',
            'duration_value' => 2,
            'duration_unit' => 'weeks',
        ])->assertRedirect();

        $template = ProjectTemplate::query()->where('name', 'Verwaltung')->firstOrFail();
        $this->assertFalse($template->use_characteristics);
        $this->assertNull($template->product_complexity);
    }

    public function test_characteristics_are_required_when_the_switch_is_on(): void
    {
        $this->actingAs($this->admin())->post(route('admin.projektschablonen.store'), [
            'name' => 'Anleitung',
            'duration_value' => 2,
            'duration_unit' => 'weeks',
            'use_characteristics' => '1',
        ])->assertSessionHasErrors(['product_complexity']);
    }

    public function test_switching_off_keeps_stored_values_and_ignores_submitted_ones(): void
    {
        $user = $this->admin();
        $template = ProjectTemplate::query()->create([
            'tenant_id' => $user->tenant_id, 'name' => 'Alt', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'use_characteristics' => true, ...$this->characteristics(2),
        ]);

        $this->actingAs($user)->post(route('admin.projektschablonen.update', $template), [
            'name' => 'Alt', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'product_complexity' => 3,
        ])->assertRedirect();

        $template->refresh();
        $this->assertFalse($template->use_characteristics);
        $this->assertSame(2, (int) $template->product_complexity);
    }

    public function test_hours_per_function_group_are_saved_with_the_main_form(): void
    {
        $user = $this->admin();
        $group = FunctionGroup::query()->create(['tenant_id' => $user->tenant_id, 'name' => 'Technische Redaktion', 'short_name' => 'TR']);
        $template = ProjectTemplate::query()->create([
            'tenant_id' => $user->tenant_id, 'name' => 'Sammel', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'unrestricted_function_groups' => true,
        ]);

        $this->actingAs($user)->post(route('admin.projektschablonen.update', $template), [
            'name' => 'Sammel', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'unrestricted_function_groups' => '1',
            'hours_present' => '1',
            'hours' => [$group->id => '12.5'],
        ])->assertRedirect();

        $this->assertSame(12.5, (float) $template->fresh()->functionGroups->firstWhere('id', $group->id)?->pivot->planned_hours);
    }

    public function test_characteristic_filter_ignores_templates_without_characteristics(): void
    {
        $user = $this->admin();
        ProjectTemplate::query()->create([
            'tenant_id' => $user->tenant_id, 'name' => 'Mit Merkmalen', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'use_characteristics' => true, ...$this->characteristics(1),
        ]);
        ProjectTemplate::query()->create([
            'tenant_id' => $user->tenant_id, 'name' => 'Ohne Merkmale', 'duration_value' => 1, 'duration_unit' => 'weeks',
            'use_characteristics' => false, ...$this->characteristics(1),
        ]);

        $this->actingAs($user)->get(route('admin.projektschablonen', ['product_complexity' => 1]))
            ->assertOk()
            ->assertSee('Mit Merkmalen')
            ->assertDontSee('Ohne Merkmale');
    }
}
