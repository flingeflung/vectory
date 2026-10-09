<?php

namespace Tests\Feature;

use App\Models\HelpArticleTranslation;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/** Hilfetext-Passagen nur für bestimmte Admin-Stufen: [[Admin1|…]], [[Admin2|…]], [[Admin3|…]] (Ralf, 2026-10-09). */
class HelpAudienceMarkerTest extends TestCase
{
    private function user(string $role): User
    {
        return User::factory()->make(['tenant_id' => Tenant::query()->firstOrFail()->id, 'role' => $role]);
    }

    private function text(): string
    {
        return "Allgemeiner Satz.\n[[Admin3|Für alle Admins, siehe [[82|Projekt-Sichtbarkeit]].]]\n[[Admin2|Nur Zentral- und Super-Admin.]]\n[[Admin1|Nur Super-Admin.]]\nSchlusssatz mit [[Admin3|Zusatz]] im Satz.";
    }

    public function test_each_level_sees_exactly_its_passages_and_the_rest_stays(): void
    {
        $normal = HelpArticleTranslation::applyAudience($this->text(), $this->user('user'));
        $this->assertSame("Allgemeiner Satz.\nSchlusssatz mit  im Satz.", $normal);

        $orgAdmin = HelpArticleTranslation::applyAudience($this->text(), $this->user('organization_admin'));
        $this->assertStringContainsString('Für alle Admins, siehe [[82|Projekt-Sichtbarkeit]].', $orgAdmin);
        $this->assertStringNotContainsString('Nur Zentral', $orgAdmin);
        $this->assertStringNotContainsString('Nur Super-Admin', $orgAdmin);
        $this->assertStringNotContainsString('[[Admin', $orgAdmin);

        $central = HelpArticleTranslation::applyAudience($this->text(), $this->user('central_admin'));
        $this->assertStringContainsString('Nur Zentral- und Super-Admin.', $central);
        $this->assertStringNotContainsString('Nur Super-Admin.', $central);

        $super = HelpArticleTranslation::applyAudience($this->text(), $this->user('super_admin'));
        $this->assertStringContainsString('Nur Super-Admin.', $super);
        $this->assertSame("Allgemeiner Satz.\nFür alle Admins, siehe [[82|Projekt-Sichtbarkeit]].\nNur Zentral- und Super-Admin.\nNur Super-Admin.\nSchlusssatz mit Zusatz im Satz.", $super);
    }

    public function test_a_removed_passage_leaves_no_empty_line_and_without_login_nothing_is_shown(): void
    {
        $this->assertSame("A\nB", HelpArticleTranslation::applyAudience("A\n[[Admin1|geheim]]\nB", null));
        $this->assertSame('A  B', HelpArticleTranslation::applyAudience('A [[Admin2|geheim]] B', null));
    }

    public function test_the_marker_inside_backticks_is_shown_as_an_example_and_not_applied(): void
    {
        $this->assertSame('Schreibweise: `[[Admin1|Text]]` und `[[Admin3|Text]]`.', HelpArticleTranslation::applyAudience('Schreibweise: `[[Admin1|Text]]` und `[[Admin3|Text]]`.', $this->user('user')));
    }

    public function test_an_unclosed_marker_is_left_alone_and_other_links_are_untouched(): void
    {
        $this->assertSame('Text [[Admin3|offen', HelpArticleTranslation::applyAudience('Text [[Admin3|offen', $this->user('user')));
        $this->assertSame('Siehe [[82|Titel]].', HelpArticleTranslation::applyAudience('Siehe [[82|Titel]].', $this->user('user')));
    }

    public function test_the_rendered_page_hides_the_passage_for_normal_users(): void
    {
        $translation = new HelpArticleTranslation(['locale' => 'de', 'body' => "Offen.\n\n[[Admin3|Nur für Administratoren.]]\n\nEnde."]);

        $this->actingAs($this->user('user'));
        $this->assertStringNotContainsString('Nur für Administratoren', $translation->bodyHtml());
        $this->assertStringContainsString('Ende.', $translation->bodyHtml());

        $this->actingAs($this->user('organization_admin'));
        $this->assertStringContainsString('Nur für Administratoren.', $translation->bodyHtml());
    }
}
