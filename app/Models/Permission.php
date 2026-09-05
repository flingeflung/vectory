<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fester, von uns gepflegter Rechte-Katalog - bewusst nicht mandanten-
 * gebunden (Mandanten weisen nur zu, sie erfinden keine neuen Rechte).
 * Siehe app/Providers/AppServiceProvider.php für die Gate-Anbindung.
 */
#[Fillable(['key', 'label'])]
class Permission extends Model
{
    //
}
