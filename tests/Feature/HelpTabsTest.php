<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpArticleTranslation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DialogId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hilfe je Reiter (Ralf, 2026-10-04): Das "?" bleibt eines je Dialog, die Hilfeseite richtet sich nach dem sichtbaren Reiter.
 */
class HelpTabsTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $key, array $routeNames, string $body): HelpArticle
    {
        $article = HelpArticle::query()->create(['key' => $key, 'route_names' => $routeNames]);
        HelpArticleTranslation::query()->create(['help_article_id' => $article->id, 'locale' => 'de', 'title' => 'Titel '.$key, 'body' => $body]);

        return $article;
    }

    public function test_keys_run_from_the_most_specific_tab_to_the_dialog(): void
    {
        $this->assertSame(['D-1#planung.auslastung', 'D-1#planung', 'D-1'], HelpArticle::dialogKeys('D-1', 'planung.auslastung'));
        $this->assertSame(['D-1'], HelpArticle::dialogKeys('D-1', ''));
        $this->assertSame([], HelpArticle::dialogKeys('', 'planung'));
    }

    public function test_help_follows_the_visible_tab_with_fallback_to_parent_tab_and_dialog(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $dialogId = DialogId::for('project-overlay');
        $this->article('dialog', [$dialogId], 'Dialogtext');
        $this->article('planung', [$dialogId.'#planung'], 'Planungstext');
        $this->article('auslastung', [$dialogId.'#planung.auslastung'], 'Auslastungstext');

        $ask = fn (string $tab) => $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => $dialogId, 'tab' => $tab]));

        $ask('planung.auslastung')->assertOk()->assertSee('Auslastungstext')->assertDontSee('Dialogtext');
        $ask('planung.terminplan')->assertOk()->assertSee('Planungstext')->assertDontSee('Auslastungstext');
        $ask('zeiten.gesamt')->assertOk()->assertSee('Dialogtext');
        $ask('')->assertOk()->assertSee('Dialogtext');
    }

    public function test_the_question_mark_appears_when_only_tab_articles_exist(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $dialogId = DialogId::for('project-overlay');
        $this->article('nur-reiter', [$dialogId.'#zeiten'], 'Zeitentext');
        DialogId::forgetCache();

        $this->assertContains($dialogId, DialogId::withHelpArticle());
    }

    public function test_the_tab_list_and_the_markers_in_the_project_overlay_match(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']);
        $this->actingAs($user);
        $project = Project::query()->create([
            'tenant_id' => $user->tenant_id, 'source_pn' => '270001', 'title' => 'P', 'status' => 0,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-12',
        ]);

        $html = $this->get(route('projekte.show', $project), ['X-Overlay' => '1'])->assertOk()->getContent();
        preg_match_all('/data-help-tab="([^"]+)"/', $html, $matches);
        $marked = collect($matches[1])->unique()->sort()->values()->all();
        $registered = collect(array_keys(config('help-tabs.project-overlay')))->sort()->values()->all();

        $this->assertSame($registered, $marked, 'config/help-tabs.php und die data-help-tab-Markierungen im Projekt-Overlay müssen übereinstimmen.');
    }

    public function test_the_same_dialog_key_cannot_be_saved_on_two_help_articles(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $first = $this->article('erste', ['D-ABCD#planung'], 'A');
        $second = $this->article('zweite', [], 'B');

        $this->post(route('admin.hilfeseiten.update', $second), ['route_names' => 'D-ABCD#planung', 'translations' => ['de' => ['title' => 'Zweite']]])
            ->assertSessionHas('help_error');
        $this->assertSame([], $second->fresh()->route_names);

        $this->post(route('admin.hilfeseiten.update', $first), ['route_names' => 'D-ABCD#planung', 'translations' => ['de' => ['title' => 'Erste']]])
            ->assertSessionMissing('help_error');
    }
}
