<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rundgang durch alle Seiten ohne Parameter (plus organisationsübergreifende Filter-Varianten): Daten einer
 * deaktivierten Organisation dürfen nirgends auftauchen (Ralf, 2026-10-03). Die Gegenprobe vorher stellt
 * sicher, dass der Rundgang die Daten überhaupt findet - sonst wäre der Test wertlos.
 */
class OrganizationDeactivationCrawlTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_PN = '279990';

    private const PROJECT_TITLE = 'ZZZ-Verstecktes-Projekt';

    private const PERSON = 'Zzzverstecktperson';

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::forgetInactiveCache();
    }

    protected function tearDown(): void
    {
        Tenant::forgetInactiveCache();
        parent::tearDown();
    }

    /** @return array<int, string> */
    private function urls(Tenant $customer): array
    {
        $urls = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')
                && $route->getName()
                && ! preg_match('/^(login|register|password|verification|logout|profile|debugbar|ignition)/', $route->getName())
                && ! str_contains($route->getName(), 'download')
                && ! str_contains($route->getName(), 'wochenwerte'))
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
            ->unique()
            ->values()
            ->all();

        // Organisationsübergreifende Sichten, die ohne Filter nur die aktive Organisation zeigen.
        return array_merge($urls, [
            '/admin/personen?tenant_id=all',
            '/admin/personen?show_inactive=1&tenant_id=all',
            '/kritische-projekte?organization_filter_submitted=1&organizations[]='.$customer->id,
            '/planung/projektplanung?organizations[]='.$customer->id.'&person=all',
            '/planung/projektplanung?displayMode=year&organizations[]='.$customer->id.'&person=all',
            '/kalender?year=2026&month=10',
            '/projekte?filter[schnellsuche]='.self::PROJECT_PN,
            '/schnellsuche?q='.self::PROJECT_PN,
        ]);
    }

    /** @return array<string, string> Seiten, auf denen die Daten der Organisation erscheinen */
    private function crawl(User $user, Tenant $customer): array
    {
        $found = [];
        $this->actingAs($user);

        foreach ($this->urls($customer) as $url) {
            $response = $this->withSession(['active_tenant_id' => $user->tenant_id])->get($url);
            if ($response->status() !== 200) {
                continue;
            }
            $content = $response->getContent();
            foreach ([self::PROJECT_PN, self::PROJECT_TITLE, self::PERSON] as $needle) {
                // Die Suchseiten geben die eingetippte Projektnummer im Suchfeld wieder - das sind keine Daten.
                if ($needle === self::PROJECT_PN && str_contains($url, self::PROJECT_PN)) {
                    continue;
                }
                if (str_contains($content, $needle)) {
                    $found[$url] = $needle;
                }
            }
        }

        return $found;
    }

    public function test_no_page_shows_data_of_a_deactivated_organization(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Kunde Rundgang', 'short_name' => 'KR']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');

        $group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'name' => 'Technische Redaktion', 'short_name' => 'TR']);
        $project = Project::query()->create(['tenant_id' => $customer->id, 'source_pn' => self::PROJECT_PN, 'title' => self::PROJECT_TITLE, 'status' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31']);
        $person = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $customer->id, 'last_name' => self::PERSON, 'first_name' => 'Anna', 'active' => true,
            'calendar_enabled' => true, 'resource_planning' => true,
        ]);
        ProjectPerson::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'project_id' => $project->id, 'function_group_id' => $group->id, 'person_id' => $person->id, 'is_primary' => true, 'planned_hours' => 10]);
        CalendarEntry::query()->create(['person_id' => $person->id, 'type' => CalendarEntry::TYPE_ABSENCE, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-09']);

        $central = User::factory()->create(['tenant_id' => $home->id, 'role' => 'central_admin']);

        // Gegenprobe: Solange aktiv, taucht die Organisation auf mindestens einer Seite auf.
        $before = $this->crawl($central, $customer);
        $this->assertNotEmpty($before, 'Der Rundgang findet die Testdaten nicht - Test wäre wertlos.');

        $customer->update(['is_active' => false]);

        $after = $this->crawl($central, $customer);
        $this->assertSame([], $after, 'Daten einer deaktivierten Organisation sichtbar auf: '.json_encode($after));
    }
}
