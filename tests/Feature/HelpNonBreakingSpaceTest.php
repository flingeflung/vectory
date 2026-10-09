<?php

namespace Tests\Feature;

use App\Models\HelpArticleTranslation;
use Tests\TestCase;

/** Hilfetexte: "z. B." bekommt beim Ausliefern ein geschütztes Leerzeichen (Ralf, 2026-10-09). */
class HelpNonBreakingSpaceTest extends TestCase
{
    public function test_zb_gets_a_non_breaking_space_but_code_stays_untouched(): void
    {
        $translation = new HelpArticleTranslation(['locale' => 'de', 'body' => "Mit Gruppen, z. B. Serien, und Z. B. Varianten.\n\n`z. B.`"]);
        $html = $translation->bodyHtml();

        $this->assertStringContainsString("z.\u{00A0}B. Serien", $html);
        $this->assertStringContainsString("Z.\u{00A0}B. Varianten", $html);
        $this->assertStringContainsString('<code>z. B.</code>', $html);
    }
}
