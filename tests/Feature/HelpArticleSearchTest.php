<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Volltextsuche im Hilfe-Editor (Ralf, 2026-10-09). */
class HelpArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $key, string $title, string $body, ?int $parent = null, string $keywords = ''): int
    {
        $id = DB::table('help_articles')->insertGetId(['key' => $key, 'parent_id' => $parent, 'position' => 0, 'route_names' => '[]', 'approved' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('help_article_translations')->insert(['help_article_id' => $id, 'locale' => 'de', 'title' => $title, 'keywords' => $keywords, 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function admin(): User
    {
        return User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']);
    }

    public function test_it_finds_title_keywords_and_text_with_path_and_snippet(): void
    {
        $parent = $this->article('suche-eltern', 'Grundlagen-Test', 'Allgemeines.');
        $this->article('suche-kind', 'Verbund-Seite', 'Das Hauptprojekt erkennen Sie am Fähnchen in der Liste.', $parent, 'Verbund, Hauptprojekt');
        $this->article('suche-anders', 'Etwas anderes', 'Hier geht es um Rechte.');

        $json = $this->actingAs($this->admin())->getJson(route('admin.hilfeseiten.suche', ['q' => 'fähnchen']))->assertOk()->json('results');
        $this->assertCount(1, $json);
        $this->assertSame('Verbund-Seite', $json[0]['title']);
        $this->assertSame('Grundlagen-Test', $json[0]['path']);
        $this->assertSame('Text', $json[0]['where']);
        $this->assertStringContainsString('Fähnchen', $json[0]['snippet']);

        $byKeyword = $this->actingAs($this->admin())->getJson(route('admin.hilfeseiten.suche', ['q' => 'Hauptprojekt']))->json('results');
        $this->assertSame('Stichwörter, Text', $byKeyword[0]['where']);

        $this->assertSame([], $this->actingAs($this->admin())->getJson(route('admin.hilfeseiten.suche', ['q' => 'x']))->json('results'));
        $this->assertSame([], $this->actingAs($this->admin())->getJson(route('admin.hilfeseiten.suche', ['q' => '100%_nirgends']))->json('results'));
    }

    public function test_title_hits_come_first_and_only_super_admins_may_search(): void
    {
        $this->article('suche-a', 'Etwas anderes', 'Hier steht das Wort Gruppe im Text.');
        $this->article('suche-b', 'Projekte gruppieren', 'Ohne Treffer im Text.');

        $results = $this->actingAs($this->admin())->getJson(route('admin.hilfeseiten.suche', ['q' => 'grupp']))->json('results');
        $this->assertSame('Projekte gruppieren', $results[0]['title']);

        $user = User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'user']);
        $this->actingAs($user)->getJson(route('admin.hilfeseiten.suche', ['q' => 'grupp']))->assertForbidden();
        $this->assertGreaterThan(0, HelpArticle::query()->count());
    }

    public function test_the_editor_page_offers_the_search_field_with_an_intact_alpine_component(): void
    {
        $this->article('suche-sicht', 'Sichtbar', 'Text.');
        $html = $this->actingAs($this->admin())->get(route('admin.hilfeseiten'))->assertOk()->assertSee('In allen Hilfetexten suchen')->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $found = false;
        foreach ((new \DOMXPath($dom))->query('//*[@x-data]') as $node) {
            if (str_contains($node->getAttribute('x-data'), 'openResult(id)')) {
                $found = true;
                $this->assertStringContainsString('window.location.href = navUrl({ article: id });', $node->getAttribute('x-data'));
            }
        }
        $this->assertTrue($found, 'x-data der Suche wurde beim Rendern abgeschnitten.');
    }
}
