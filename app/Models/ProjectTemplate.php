<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projektschablone (Nachfolger von Viettos GA-Kategorien) - Erfahrungswerte-
 * Eintrag: Merkmale + geschätzte Brutto-Bearbeitungsdauer, siehe Migration.
 * Jedes Merkmal ist eine feste Ordinalskala (1 = günstig für die Dauer, der
 * höchste Wert = ungünstig) - die *Options()-Methoden liefern Label + Farbe
 * je Stufe, analog zu Viettos get_anteilXX()-Funktionen.
 */
#[Fillable([
    'tenant_id', 'name', 'format', 'reusable_content_share', 'languages_count', 'product_maturity',
    'product_change_delays', 'contact_availability', 'localizer_availability', 'software_share',
    'product_complexity', 'print_variants_count', 'images_count', 'duration_value', 'duration_unit',
    'remarks', 'active', 'created_by_user_id', 'updated_by_user_id',
])]
class ProjectTemplate extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'duration_value' => 'decimal:1'];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function durationUnitOptions(): array
    {
        return ['weeks' => __('Woche(n)'), 'months' => __('Monate')];
    }

    public static function formatOptions(): array
    {
        return [
            1 => __('Online'),
            2 => __('Print'),
            3 => __('Online und Print'),
        ];
    }

    public static function reusableContentShareOptions(): array
    {
        return [
            1 => ['label' => __('hoch 75–100 %'), 'color' => 'green'],
            2 => ['label' => __('mittel 50–75 %'), 'color' => 'lime'],
            3 => ['label' => __('mäßig 25–50 %'), 'color' => 'amber'],
            4 => ['label' => __('wenig 0–25 %'), 'color' => 'red'],
        ];
    }

    public static function languagesCountOptions(): array
    {
        return [
            1 => ['label' => __('0–1'), 'color' => 'green'],
            2 => ['label' => __('2'), 'color' => 'amber'],
            3 => ['label' => __('3 oder mehr'), 'color' => 'red'],
        ];
    }

    public static function productMaturityOptions(): array
    {
        return [
            1 => ['label' => __('fertig 96–100 %'), 'color' => 'green'],
            2 => ['label' => __('wenig fertig 50–95 %'), 'color' => 'amber'],
            3 => ['label' => __('nicht fertig < 50 %'), 'color' => 'red'],
        ];
    }

    public static function productChangeDelaysOptions(): array
    {
        return [
            1 => ['label' => __('keine'), 'color' => 'green'],
            2 => ['label' => __('wenig bis mittel'), 'color' => 'amber'],
            3 => ['label' => __('erheblich'), 'color' => 'red'],
        ];
    }

    public static function contactAvailabilityOptions(): array
    {
        return [
            1 => ['label' => __('hoch'), 'color' => 'green'],
            2 => ['label' => __('mittel'), 'color' => 'amber'],
            3 => ['label' => __('gering'), 'color' => 'red'],
        ];
    }

    /**
     * Zusätzlich zu hoch/mittel/gering ein "nicht zutreffend" (Ralf,
     * 2026-09-18) - nicht jedes Projekt braucht überhaupt eine
     * Lokalisierung (z.B. reine Einzelmarkt-Projekte), anders als bei
     * contactAvailabilityOptions() (PT/PM sind immer relevant).
     */
    public static function localizerAvailabilityOptions(): array
    {
        return [
            ...self::contactAvailabilityOptions(),
            4 => ['label' => __('nicht zutreffend'), 'color' => 'gray'],
        ];
    }

    public static function softwareShareOptions(): array
    {
        return [
            1 => ['label' => __('keine'), 'color' => 'green'],
            2 => ['label' => __('wenig bis mittel'), 'color' => 'amber'],
            3 => ['label' => __('erheblich'), 'color' => 'red'],
        ];
    }

    public static function productComplexityOptions(): array
    {
        return [
            1 => ['label' => __('gering'), 'color' => 'green'],
            2 => ['label' => __('mittel'), 'color' => 'amber'],
            3 => ['label' => __('hoch'), 'color' => 'red'],
        ];
    }

    public static function printVariantsCountOptions(): array
    {
        return [
            1 => ['label' => __('keine/gering'), 'color' => 'green'],
            2 => ['label' => __('mittel'), 'color' => 'amber'],
            3 => ['label' => __('hoch'), 'color' => 'red'],
        ];
    }

    public static function imagesCountOptions(): array
    {
        return [
            1 => ['label' => __('0–5'), 'color' => 'green'],
            2 => ['label' => __('6–15'), 'color' => 'lime'],
            3 => ['label' => __('16–35'), 'color' => 'amber'],
            4 => ['label' => __('> 35'), 'color' => 'red'],
        ];
    }

    /**
     * @return array<string, array{field: string, options: callable}>
     */
    public static function characteristicFields(): array
    {
        return [
            'reusable_content_share' => ['label' => __('Anteil wiederverwendbarer Inhalt'), 'options' => self::reusableContentShareOptions()],
            'product_complexity' => ['label' => __('Produktkomplexität'), 'options' => self::productComplexityOptions()],
            'product_maturity' => ['label' => __('Entwicklungsstand Produkt'), 'options' => self::productMaturityOptions()],
            'software_share' => ['label' => __('Anteil Software'), 'options' => self::softwareShareOptions()],
            'languages_count' => ['label' => __('Anzahl Märkte/Sprachen'), 'options' => self::languagesCountOptions()],
            'product_change_delays' => ['label' => __('Verzögerungen durch Produktänderungen'), 'options' => self::productChangeDelaysOptions()],
            'contact_availability' => ['label' => __('Verfügbarkeit Ansprechpartner (PT/PM)'), 'options' => self::contactAvailabilityOptions()],
            'localizer_availability' => ['label' => __('Verfügbarkeit Lokalisierer'), 'options' => self::localizerAvailabilityOptions()],
            'print_variants_count' => ['label' => __('Anzahl Printvarianten'), 'options' => self::printVariantsCountOptions()],
            'images_count' => ['label' => __('Anzahl Abbildungen'), 'options' => self::imagesCountOptions()],
        ];
    }

    /**
     * Nur die vier Merkmale, die in Vietto auch als Listen-Filter dienten
     * (siehe gakat.php) - die übrigen acht sind reine Anzeige-Merkmale.
     */
    public static function filterableFields(): array
    {
        return ['reusable_content_share', 'product_complexity', 'product_maturity', 'software_share'];
    }
}
