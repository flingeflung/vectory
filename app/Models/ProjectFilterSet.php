<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benannte Projektfilter-Kombinationen je Nutzer - unabhängig von den
 * Anzeigefilter-Sets (DisplayFilterSet), siehe Migration.
 */
#[Fillable(['user_id', 'name', 'config', 'is_active'])]
class ProjectFilterSet extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Legt für neue Nutzer einmalig ein aktives "Standard"-Filterset an -
     * gleiches Muster wie ProjectColumnCatalog::ensureDefaultSetFor().
     */
    public static function ensureDefaultSetFor(User $user): self
    {
        $activeSet = self::query()->where('user_id', $user->id)->where('is_active', true)->first();

        if ($activeSet) {
            return $activeSet;
        }

        return self::firstOrCreate(
            ['user_id' => $user->id, 'name' => __('Standard')],
            ['config' => [], 'is_active' => true]
        );
    }
}
