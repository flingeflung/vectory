<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Automatischer Rundlauf: jede Rolle ruft jede Seite auf (Seiten ohne Parameter plus die Seiten zu einem
 * Beispiel-Projekt, einer Beispiel-Person und einer Projektgruppe). Geprüft wird nur Grobes:
 * 1. Keine Seite darf mit einem Serverfehler (5xx) antworten.
 * 2. Wer das Recht für eine Seite nicht hat (Zugriffsprüfung `can:` an der Route), muss abgewiesen werden.
 * Inhalte und Logik prüft das nicht - das machen die Fachtests.
 */
class FullRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['user', 'organization_admin', 'central_admin', 'super_admin'];

    /** Seiten, die Tokens/Signaturen brauchen, Dateien liefern oder Fremddienste ansprechen. */
    private const SKIP_NAME = '/^(login|register|password|verification|logout|debugbar|ignition|sanctum|storage)/';

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

    /** @return array<string, mixed> Beispieldaten für die Routenparameter */
    private function seedData(Tenant $home): array
    {
        $group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'name' => 'Technische Redaktion', 'short_name' => 'TR']);
        $project = Project::query()->create(['tenant_id' => $home->id, 'source_pn' => '279991', 'title' => 'Rundlauf-Projekt', 'status' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31']);
        $person = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id, 'last_name' => 'Rundlauf', 'first_name' => 'Rita', 'active' => true,
            'calendar_enabled' => true, 'resource_planning' => true,
        ]);
        $projectGroup = ProjectGroup::query()->create(['tenant_id' => $home->id, 'name' => 'Rundlauf-Gruppe', 'is_verbund' => false]);

        return [
            'project' => $project->id,
            'person' => $person->id,
            'group' => $projectGroup->id,
            'functionGroup' => $group->id,
        ];
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> URL und Zugriffsprüfungen (`can:`) je Seite */
    private function pages(array $ids): array
    {
        $pages = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! $route->getName()
                || preg_match(self::SKIP_NAME, $route->getName())
                || str_contains($route->getName(), 'download')) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');
            foreach ($route->parameterNames() as $name) {
                if (! isset($ids[$name])) {
                    // Parameter ohne Beispieldaten (Token, Signatur, Workflow-Schritt ...): Seite überspringen.
                    continue 2;
                }
                $uri = preg_replace('/\{'.$name.'\??(:[a-z]+)?\}/', (string) $ids[$name], $uri);
            }

            $abilities = collect($route->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))
                ->map(fn ($m) => substr($m, 4))
                ->values()
                ->all();

            $pages[] = [$uri, $abilities];
        }

        return $pages;
    }

    public function test_every_role_can_open_every_page_without_server_error(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $ids = $this->seedData($home);
        $pages = $this->pages($ids);

        $this->assertGreaterThan(50, count($pages), 'Der Rundlauf findet auffällig wenige Seiten.');

        $problems = [];
        $checked = 0;
        foreach (self::ROLES as $role) {
            $user = User::factory()->create(['tenant_id' => $home->id, 'role' => $role]);
            $this->actingAs($user);

            foreach ($pages as [$url, $abilities]) {
                $status = $this->withSession(['active_tenant_id' => $home->id])->get($url)->status();
                $checked++;

                if ($status >= 500) {
                    $problems[] = "$role: $url -> Serverfehler $status";

                    continue;
                }

                $allowed = collect($abilities)->every(fn ($ability) => Gate::forUser($user)->allows($ability));
                if (! $allowed && $status === 200) {
                    $problems[] = "$role: $url -> sollte abgewiesen werden (".implode(', ', $abilities).'), antwortet aber 200';
                }
            }
        }

        $this->assertGreaterThan(200, $checked);
        $this->assertSame([], $problems, "Rundlauf-Funde:\n".implode("\n", $problems));
    }
}
