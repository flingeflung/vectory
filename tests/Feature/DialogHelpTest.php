<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpArticleTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DialogId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DialogHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dialog_id_ignores_record_counter_and_is_stable(): void
    {
        $this->assertSame(DialogId::for('projektgruppen-panel-12'), DialogId::for('projektgruppen-panel-7'));
        $this->assertSame(DialogId::for('project-overlay'), DialogId::for('project-overlay'));
        $this->assertMatchesRegularExpression('/^D-[0-9A-Z]{4}$/', DialogId::for('project-overlay'));
    }

    public function test_every_dialog_type_has_its_own_id(): void
    {
        $names = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match_all('/<x-modal\s+name="([^"]+)"/', $file->getContents(), $matches)) {
                foreach ($matches[1] as $name) {
                    // Dynamische Teile (Datensatz-Zähler) abschneiden, andere dynamische Namen auslassen.
                    $static = preg_replace('/-\{\{[^}]*\}\}$/', '', $name);
                    if (! str_contains($static, '{{')) {
                        $names[$static] = true;
                    }
                }
            }
        }

        $this->assertGreaterThan(20, count($names));

        $byId = collect(array_keys($names))->groupBy(fn ($name) => DialogId::for($name));
        $collisions = $byId->filter(fn ($group) => $group->count() > 1)->map(fn ($group) => $group->all());

        $this->assertTrue($collisions->isEmpty(), 'Doppelte Dialog-IDs: '.json_encode($collisions->all()));
    }

    public function test_help_prefers_article_of_the_dialog_and_falls_back_to_the_page(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $dialogId = DialogId::for('project-overlay');

        $page = HelpArticle::query()->create(['key' => 'seite', 'route_names' => ['projekte']]);
        HelpArticleTranslation::query()->create(['help_article_id' => $page->id, 'locale' => 'de', 'title' => 'Hilfe zur Seite', 'body' => 'Seitentext']);
        $dialog = HelpArticle::query()->create(['key' => 'dialog', 'route_names' => [$dialogId]]);
        HelpArticleTranslation::query()->create(['help_article_id' => $dialog->id, 'locale' => 'de', 'title' => 'Hilfe zum Dialog', 'body' => 'Dialogtext']);

        $this->actingAs($user);

        $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => $dialogId]))
            ->assertOk()->assertSee('Dialogtext')->assertDontSee('Seitentext');

        $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => DialogId::for('irgendein-anderer-dialog')]))
            ->assertOk()->assertSee('Seitentext')->assertSee('noch keine eigene Hilfeseite');

        $this->get(route('hilfe', ['route' => 'projekte']))
            ->assertOk()->assertSee('Seitentext');
    }
}
