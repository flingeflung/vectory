<?php

namespace App\Models;

use App\Enums\GraphicOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\GraphicOrderObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'project_id', 'graphic_order_status_id', 'legacy_id', 'image_count',
    'description', 'due_date', 'initiated_by_person_id', 'illustrator_person_id',
    'done_at', 'completed_by_person_id',
])]
#[ObservedBy(GraphicOrderObserver::class)]
class GraphicOrder extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'done_at' => 'datetime',
            'graphic_order_status_id' => GraphicOrderStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Alias für graphic_order_status_id (enum-gecastet) - liest sich in
     * Views natürlicher als der Spaltenname.
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => $this->graphic_order_status_id);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'initiated_by_person_id');
    }

    public function illustrator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'illustrator_person_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'completed_by_person_id');
    }
}
