<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'legacy_id', 'country_iso', 'country_name', 'country_short_name', 'language_code', 'language_name', 'no_translation', 'sort'])]
class Market extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'no_translation' => 'boolean',
        ];
    }

    public function label(): string
    {
        return "{$this->country_iso} ".strtolower($this->language_code)." – {$this->country_name}; {$this->language_name}";
    }

    /**
     * Kompaktes Format für die Projektdetails, entspricht Viettos
     * get_pndetails_maerkte(): fetter ISO+Sprachcode, "*" bei
     * no_translation, kurzer Ländername.
     */
    public function shortLabel(): string
    {
        $star = $this->no_translation ? '*' : '';

        return "{$this->country_iso}".strtolower($this->language_code)."{$star} {$this->country_short_name}";
    }

    /**
     * Nicht jede Land/Sprache-Kombination hat ein Icon (schon in Vietto
     * selbst fehlen z.B. für AE/JP welche) – dann greift der Text-Fallback.
     */
    public function iconUrl(): ?string
    {
        $path = 'images/markticons/'.strtolower($this->country_iso).'.png';

        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
