<?php

namespace App\Support;

/**
 * Manche Vietto-Stammdaten enthalten Bezeichnungen interner Systeme/Marken
 * des Referenzkunden (z.B. "cosima"), die in Vectory als extern vermarktetem
 * Produkt nirgends auftauchen dürfen. Zentrale Stelle, um solche Begriffe bei
 * jedem Import konsequent herauszufiltern statt es an jeder Importstelle neu
 * zu vergessen.
 */
class NameSanitizer
{
    /**
     * @var list<string>
     */
    private const STRIP_TERMS = ['cosima'];

    public static function clean(string $name): string
    {
        $cleaned = $name;

        foreach (self::STRIP_TERMS as $term) {
            $cleaned = trim(preg_replace('/\s*'.preg_quote($term, '/').'/i', '', $cleaned));
        }

        return $cleaned;
    }
}
