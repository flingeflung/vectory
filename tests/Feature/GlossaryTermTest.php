<?php

namespace Tests\Feature;

use App\Models\GlossaryTerm;
use App\Models\HelpArticleTranslation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Begriffsverzeichnis (Ralf, 2026-10-04).
 */
class GlossaryTermTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => 'super_admin']);
    }

    public function test_every_seeded_term_points_to_an_existing_page(): void
    {
        foreach (GlossaryTerm::query()->get() as $term) {
            $this->assertNotNull($term->url(), "Begriff {$term->term}: Seite {$term->route_name} fehlt.");
        }
    }

    public function test_a_term_becomes_a_new_tab_link_with_tooltip_and_unknown_terms_stay_text(): void
    {
        $this->actingAs($this->superAdmin());
        GlossaryTerm::forgetCache();

        $html = GlossaryTerm::link('grundlast');
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString(route('planung.grundlast'), $html);
        $this->assertStringContainsString('title="', $html);
        $this->assertSame('Unbekannt', GlossaryTerm::link('Unbekannt'));
        $this->assertStringContainsString('>Eintrag<', GlossaryTerm::link('Grundlast', 'Eintrag'));
    }

    public function test_help_text_syntax_is_replaced(): void
    {
        $this->actingAs($this->superAdmin());
        GlossaryTerm::forgetCache();
        $translation = new HelpArticleTranslation(['locale' => 'de', 'title' => 'T', 'body' => 'Siehe ((Feiertage)) und ((Grundlast|die Grundlast)).']);

        $html = $translation->bodyHtml();
        $this->assertStringContainsString(route('admin.feiertage'), $html);
        $this->assertStringContainsString('>die Grundlast<', $html);
        $this->assertStringNotContainsString('((', $html);
    }

    public function test_admin_page_saves_and_rejects_duplicates(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.begriffe'))->assertOk()->assertSee('Grundlast');
        $this->post(route('admin.begriffe.store'), ['term' => 'Schrittdauer', 'route_name' => 'admin.workflows'])->assertRedirect();
        $this->assertDatabaseHas('glossary_terms', ['term' => 'Schrittdauer']);
        $this->post(route('admin.begriffe.store'), ['term' => 'schrittdauer', 'route_name' => 'admin.workflows'])->assertSessionHasErrors('term');
    }
}
