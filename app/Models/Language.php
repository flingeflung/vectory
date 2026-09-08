<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Globale Sprachen-Referenzliste, siehe Country-Modell für die Begründung.
 */
#[Fillable(['code', 'name', 'sort'])]
class Language extends Model
{
    //
}
