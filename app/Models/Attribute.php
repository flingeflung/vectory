<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\AttributeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'section', 'key', 'label', 'data_type', 'multiple', 'sort', 'available_in_mail_templates'])]
#[ObservedBy(AttributeObserver::class)]
class Attribute extends Model
{
    use BelongsToTenant;

    /**
     * Bereich im Projekt, in dem das Attribut angezeigt wird (Ralf,
     * 2026-09-10) - nur SECTION_TYPSPEZIFISCH wird zusätzlich einzelnen
     * Projektarten zugewiesen (siehe projectTypeSubs()); die anderen beiden
     * gelten immer für alle Projekte.
     */
    public const SECTION_STAMMDATEN = 'stammdaten';

    public const SECTION_ABLAUFDATEN = 'ablaufdaten';

    public const SECTION_TYPSPEZIFISCH = 'typspezifisch';

    public const SECTIONS = [self::SECTION_STAMMDATEN, self::SECTION_ABLAUFDATEN, self::SECTION_TYPSPEZIFISCH];

    /**
     * Feldtypen, gegen Viettos tatsächliche Projekt-Formularfelder
     * geprüft (Baujahr, MatNr, Farbigkeit, Heftung, Verpackung, ...) -
     * DATA_TYPE_SELECT deckt sowohl Einfach- als auch Mehrfachauswahl ab
     * (siehe $multiple), Vietto hatte dafür zwei getrennte, teils
     * hartkodierte Pulldown-Varianten, hier bewusst vereinheitlicht.
     */
    public const DATA_TYPE_TEXT = 'text';

    public const DATA_TYPE_TEXTAREA = 'textarea';

    public const DATA_TYPE_NUMBER = 'number';

    public const DATA_TYPE_DATE = 'date';

    public const DATA_TYPE_BOOLEAN = 'boolean';

    public const DATA_TYPE_SELECT = 'select';

    public const DATA_TYPES = [
        self::DATA_TYPE_TEXT,
        self::DATA_TYPE_TEXTAREA,
        self::DATA_TYPE_NUMBER,
        self::DATA_TYPE_DATE,
        self::DATA_TYPE_BOOLEAN,
        self::DATA_TYPE_SELECT,
    ];

    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'available_in_mail_templates' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort');
    }

    public function projectTypeSubs(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTypeSub::class, 'attribute_project_type');
    }
}
