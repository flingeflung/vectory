<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_id', 'title', 'sort'])]
class ChecklistSection extends Model
{
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(ChecklistPoint::class)->orderBy('sort');
    }
}
