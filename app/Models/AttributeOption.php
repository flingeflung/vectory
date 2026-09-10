<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Auswahlmöglichkeit für ein Pulldown-Attribut (Attribute::DATA_TYPE_
 * SELECT) - kein eigener Mandanten-Scope nötig, hängt schon über
 * attribute_id am (mandantenscoped) Attribute.
 */
#[Fillable(['attribute_id', 'value', 'label', 'sort'])]
class AttributeOption extends Model
{
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
