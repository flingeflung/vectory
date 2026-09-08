<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Globale Länder-Referenzliste (ISO-genormt, für alle Kunden gleich) -
 * bewusst OHNE tenant_id, siehe Migration. Bildet zusammen mit languages()
 * den Markt-Katalog (App\Http\Controllers\Admin\MarketController): welche
 * Sprachen sind in diesem Land relevant. Anhaken einer Zeile für ein
 * Länderset legt bei Bedarf automatisch den passenden, pro Kunde
 * editierbaren Market-Datensatz an.
 */
#[Fillable(['iso', 'name', 'short_name'])]
class Country extends Model
{
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'country_languages')->orderBy('languages.sort');
    }

    /**
     * Dateiname i.d.R. der ISO-Code; Sonderfälle ohne offiziellen ISO-Code
     * (z.B. Nordzypern, nicht international anerkannt) verwenden ersatzweise
     * einen aus dem Namen abgeleiteten Slug - siehe Market::iconUrl() für
     * das gleiche Verzeichnis/den gleichen Text-Fallback.
     */
    public function iconUrl(): ?string
    {
        $key = $this->iso ? strtolower($this->iso) : \Illuminate\Support\Str::slug($this->name);
        $path = 'images/markticons/'.$key.'.png';

        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
