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

    public function test_tab_keys_without_a_matching_tab_are_reported(): void
    {
        $dialogId = DialogId::for('project-overlay');
        $this->assertSame([], $this->article('ok', [$dialogId.'#planung.auslastung', $dialogId, 'projekte'], 'x')->orphanedTabKeys());
        $this->assertSame([$dialogId.'#planung.weg', 'D-ZZZZ#planung'], $this->article('kaputt', [$dialogId.'#planung.weg', 'D-ZZZZ#planung'], 'x')->orphanedTabKeys());
    }

    public function test_a_nested_article_shows_a_breadcrumb_path(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $top = $this->article('oben', [], 'o');
        $mid = $this->article('mitte', [], 'm');
        $leaf = $this->article('blatt', ['seite-x'], 'b');
        $mid->update(['parent_id' => $top->id]);
        $leaf->update(['parent_id' => $mid->id]);

        $this->get(route('hilfe', ['route' => 'seite-x']))->assertOk()
            ->assertSee('data-help-key="oben"', false)->assertSee('data-help-key="mitte"', false);
        $this->get(route('hilfe', ['route' => 'oben-gibt-es-nicht']))->assertOk()->assertDontSee('aria-label="Pfad"', false);
    }

    public function test_new_pages_can_be_inserted_below_a_node_as_sibling_or_as_first_child(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $a = HelpArticle::query()->create(['key' => 'a', 'route_names' => [], 'position' => 0]);
        $b = HelpArticle::query()->create(['key' => 'b', 'route_names' => [], 'position' => 1]);
        $a1 = HelpArticle::query()->create(['key' => 'a1', 'route_names' => [], 'parent_id' => $a->id, 'position' => 0]);

        $this->post(route('admin.hilfeseiten.store'), ['title' => 'Kind', 'after' => $a->id, 'mode' => 'child'])->assertRedirect();
        $child = HelpArticle::query()->where('parent_id', $a->id)->orderBy('position')->first();
        $this->assertSame('kind', $child->key);
        $this->assertSame(0, $child->position);
        $this->assertSame(1, $a1->fresh()->position);

        $this->post(route('admin.hilfeseiten.store'), ['title' => 'Nachbar', 'after' => $a->id, 'mode' => 'sibling'])->assertRedirect();
        $neighbour = HelpArticle::query()->where('key', 'nachbar')->first();
        $this->assertNull($neighbour->parent_id);
        $this->assertSame(1, $neighbour->position);
        $this->assertSame(2, $b->fresh()->position);

        $deep = $this->article('tief', [], 'x');
        $deep->update(['parent_id' => HelpArticle::query()->create(['key' => 'e3', 'route_names' => [], 'position' => 9, 'parent_id' => HelpArticle::query()->create(['key' => 'e2', 'route_names' => [], 'position' => 9, 'parent_id' => $a->id])->id])->id]);
        $before = HelpArticle::query()->count();
        $this->post(route('admin.hilfeseiten.store'), ['title' => 'Zu tief', 'after' => $deep->id, 'mode' => 'child']);
        $this->assertSame($before, HelpArticle::query()->count());
    }

    public function test_help_links_by_number_or_by_unique_title_and_flag_ambiguous_titles(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $one = $this->article('eins', [], 'x');
        $two = HelpArticle::query()->create(['key' => 'zwei', 'route_names' => []]);
        HelpArticleTranslation::query()->create(['help_article_id' => $two->id, 'locale' => 'de', 'title' => 'Titel eins', 'body' => 'y']);
        $unique = $this->article('einzig', [], 'z');
        $unique->translations()->update(['title' => 'Nur einmal']);

        $body = "[[{$one->id}]] [[{$two->id}|Eigener Text]] [[Titel eins]] [[Nur einmal]] [[9999]]";
        $html = (new HelpArticleTranslation(['locale' => 'de', 'title' => 'T', 'body' => $body]))->bodyHtml();

        $this->assertStringContainsString('data-help-key="eins">Titel eins</a>', $html);
        $this->assertStringContainsString('data-help-key="zwei">Eigener Text</a>', $html);
        $this->assertStringContainsString('data-help-key="einzig">Nur einmal</a>', $html);
        $this->assertStringContainsString('mehrfach vor', $html);
        $this->assertStringContainsString('Keine Hilfeseite mit dieser Nummer', $html);
    }

    public function test_code_examples_in_help_text_stay_plain_text(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $html = (new HelpArticleTranslation(['locale' => 'de', 'title' => 'T', 'body' => 'Schreiben Sie `[[42]]` oder `((Grundlast))`.']))->bodyHtml();

        $this->assertStringContainsString('<code>[[42]]</code>', $html);
        $this->assertStringContainsString('<code>((Grundlast))</code>', $html);
    }

    public function test_the_collapsed_state_of_the_properties_is_remembered_per_page_and_user(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']);
        $this->actingAs($user);
        $one = $this->article('eins', [], 'x');
        $two = $this->article('zwei', [], 'y');

        $this->postJson(route('admin.hilfeseiten.metazustand'), ['id' => $one->id, 'open' => false])->assertOk();
        $this->postJson(route('admin.hilfeseiten.baumzustand'), ['collapsed' => [$two->id]])->assertOk();

        $config = \App\Models\UserPreference::configFor($user->id, \App\Models\UserPreference::HELP_TREE);
        $this->assertSame([$one->id], $config['meta_collapsed']);
        $this->assertSame([$two->id], $config['collapsed']);

        $this->get(route('admin.hilfeseiten', ['article' => $one->id]))->assertOk()->assertSee('metaOpen: false', false);
        $this->get(route('admin.hilfeseiten', ['article' => $two->id]))->assertOk()->assertSee('metaOpen: true', false);

        $this->postJson(route('admin.hilfeseiten.metazustand'), ['id' => $one->id, 'open' => true])->assertOk();
        $this->assertSame([], \App\Models\UserPreference::configFor($user->id, \App\Models\UserPreference::HELP_TREE)['meta_collapsed']);
    }

    public function test_the_time_tracking_dialog_tabs_match_the_config(): void
    {
        $marked = collect(['project-hours-body', 'project-jobs-body', 'project-percentage-body'])
            ->map(function (string $file) {
                preg_match('/data-help-tab="([^"]+)"/', file_get_contents(resource_path("views/projekte/partials/{$file}.blade.php")), $match);

                return $match[1] ?? null;
            })->sort()->values()->all();

        $this->assertSame(collect(array_keys(config('help-tabs.project-time-tracking')))->sort()->values()->all(), $marked);
    }

    public function test_the_super_admin_sees_who_can_open_the_main_page(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']));
        $this->article('stunden', ['D-78FH#buchungen'], 'x');
        $this->article('regeln', ['admin.rechte'], 'y');

        $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => 'D-78FH', 'tab' => 'buchungen']))
            ->assertOk()->assertSee('Hauptseite sichtbar für:')->assertSee('nur ihre eigenen Buchungen', false);
        $this->get(route('hilfe', ['route' => 'admin.rechte']))
            ->assertOk()->assertSee('Administratoren (Organisations-, Zentral- und Super-Admin)');
    }

    public function test_help_access_descriptions_only_name_existing_rights_and_pages(): void
    {
        $rights = \App\Models\Permission::query()->pluck('key')->all();
        foreach (config('help-access') as $key => $text) {
            preg_match_all('/\(([a-z_]+(?:\.[a-z_]+)+)\)/', $text, $matches);
            foreach ($matches[1] as $right) {
                $this->assertContains($right, $rights, "config/help-access.php nennt das Recht {$right} (bei {$key}), das es nicht gibt.");
            }
            if (! str_starts_with($key, 'D-')) {
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($key), "config/help-access.php: die Seite {$key} gibt es nicht.");
            }
        }
    }
}
