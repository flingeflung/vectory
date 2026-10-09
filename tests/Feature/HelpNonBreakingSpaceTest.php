<?php

namespace Tests\Feature;

use App\Models\HelpArticleTranslation;
use Tests\TestCase;

/** Hilfetexte: Kürzel und Zahl-Einheit-Paare bekommen beim Ausliefern geschützte Leerzeichen (Ralf, 2026-10-09). */
class HelpNonBreakingSpaceTest extends TestCase
{
    private function html(string $body): string
    {
        return (new HelpArticleTranslation(['locale' => 'de', 'body' => $body]))->bodyHtml();
    }

    public function test_abbreviations_keep_together_but_code_stays_untouched(): void
    {
        $html = $this->html("Mit Gruppen, z. B. Serien, und Z. B. Varianten, d. h. alle, i. d. R. zwei.\n\n`z. B.`");

        $this->assertStringContainsString("z.\u{00A0}B. Serien", $html);
        $this->assertStringContainsString("Z.\u{00A0}B. Varianten", $html);
        $this->assertStringContainsString("d.\u{00A0}h. alle", $html);
        $this->assertStringContainsString("i.\u{00A0}d.\u{00A0}R. zwei", $html);
        $this->assertStringContainsString('<code>z. B.</code>', $html);
    }

    public function test_numbers_stay_with_their_unit_and_words_are_not_touched(): void
    {
        $html = $this->html('Es sind 10 Std. und 5 h, 3 Tage, 20 % und 14:30 Uhr geplant, siehe Nr. 16. Dann 5 hat und 2 Tagebücher.');

        $this->assertStringContainsString("10\u{00A0}Std.", $html);
        $this->assertStringContainsString("5\u{00A0}h,", $html);
        $this->assertStringContainsString("3\u{00A0}Tage", $html);
        $this->assertStringContainsString("20\u{00A0}%", $html);
        $this->assertStringContainsString("14:30\u{00A0}Uhr", $html);
        $this->assertStringContainsString("Nr.\u{00A0}16", $html);
        $this->assertStringContainsString('5 hat', $html);
        $this->assertStringContainsString('2 Tagebücher', $html);
    }

    public function test_html_attributes_are_not_changed(): void
    {
        $html = $this->html('Siehe [Link](https://example.org/a "z. B. Titel 10 Std.") hier.');

        $this->assertStringContainsString('title="z. B. Titel 10 Std."', $html);
    }
}
