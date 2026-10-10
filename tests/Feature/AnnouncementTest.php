<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccessLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mitteilungen vom Admin für die Startseite (Ralf, 2026-10-10). */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function otherTenant(): Tenant
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');

        return Tenant::query()->create(['name' => 'Andere Organisation', 'short_name' => 'AND', 'active' => true]);
    }

    public function test_central_admin_chooses_the_organizations_and_only_their_users_see_the_message(): void
    {
        $home = Tenant::query()->firstOrFail();
        $other = $this->otherTenant();
        $admin = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::CENTRAL_ADMIN]);
        $homeUser = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::USER]);
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'role' => AccessLevel::USER]);

        $this->actingAs($admin)->post(route('admin.mitteilungen.store'), ['text' => 'Wartung am Freitag'])->assertRedirect();
        $announcement = Announcement::query()->firstOrFail();
        $this->assertCount(0, $announcement->tenants, 'Ohne Auswahl erreicht eine neue Mitteilung zunächst niemanden.');

        $this->post(route('admin.mitteilungen.update', $announcement), ['text' => 'Wartung am Freitag', 'tenant_ids' => [$other->id]])->assertRedirect();

        $this->actingAs($otherUser)->get(route('dashboard'))->assertOk()->assertSee('Wartung am Freitag');
        $this->actingAs($homeUser)->get(route('dashboard'))->assertOk()->assertDontSee('Wartung am Freitag');
    }

    public function test_expired_message_is_not_shown_but_last_day_still_is(): void
    {
        $home = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::USER]);
        $expired = Announcement::query()->create(['text' => 'Gestern abgelaufen', 'ends_on' => today()->subDay()]);
        $lastDay = Announcement::query()->create(['text' => 'Heute letzter Tag', 'ends_on' => today()]);
        $expired->tenants()->sync([$home->id]);
        $lastDay->tenants()->sync([$home->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Heute letzter Tag')
            ->assertDontSee('Gestern abgelaufen');
    }

    public function test_organization_admin_reaches_only_the_own_organization_and_sees_only_matching_messages(): void
    {
        $home = Tenant::query()->firstOrFail();
        $other = $this->otherTenant();
        $organizationAdmin = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::ORGANIZATION_ADMIN]);
        $foreign = Announcement::query()->create(['text' => 'Nur für andere']);
        $foreign->tenants()->sync([$other->id]);
        $shared = Announcement::query()->create(['text' => 'Für beide']);
        $shared->tenants()->sync([$home->id, $other->id]);

        $this->actingAs($organizationAdmin)->post(route('admin.mitteilungen.store'), ['text' => 'Eigene Mitteilung'])->assertRedirect();
        $own = Announcement::query()->where('text', 'Eigene Mitteilung')->firstOrFail();
        $this->assertSame([$home->id], $own->tenants()->pluck('tenants.id')->all());

        // Versuch, eine andere Organisation mitzugeben, wird ignoriert.
        $this->post(route('admin.mitteilungen.update', $own), ['text' => 'Eigene Mitteilung', 'tenant_ids' => [$other->id]])->assertRedirect();
        $this->assertSame([$home->id], $own->tenants()->pluck('tenants.id')->all());

        $this->get(route('admin.mitteilungen'))
            ->assertOk()
            ->assertSee('Eigene Mitteilung')
            ->assertDontSee('Nur für andere')
            ->assertDontSee('Für beide');
        $this->post(route('admin.mitteilungen.update', $foreign), ['text' => 'Geändert'])->assertNotFound();
        $this->delete(route('admin.mitteilungen.destroy', $shared))->assertNotFound();
    }

    public function test_normal_users_cannot_manage_messages(): void
    {
        $home = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::USER]);

        $this->actingAs($user)->get(route('admin.mitteilungen'))->assertRedirect();
        $this->post(route('admin.mitteilungen.store'), ['text' => 'Hallo'])->assertRedirect();
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_message_can_be_deleted(): void
    {
        $home = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::SUPER_ADMIN]);
        $announcement = Announcement::query()->create(['text' => 'Weg damit']);
        $announcement->tenants()->sync([$home->id]);

        $this->actingAs($admin)->delete(route('admin.mitteilungen.destroy', $announcement))->assertRedirect(route('admin.mitteilungen'));
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseMissing('announcement_tenant', ['announcement_id' => $announcement->id]);
    }
}
