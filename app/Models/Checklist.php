<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'active', 'sort'])]
class Checklist extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class)->orderBy('sort');
    }

    public function projectChecklists(): HasMany
    {
        return $this->hasMany(ProjectChecklist::class);
    }

    public function pointsCount(): int
    {
        return $this->sections->sum(fn (ChecklistSection $section) => $section->points->count());
    }
}
