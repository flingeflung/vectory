<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'graphic_order_status_id', 'legacy_id', 'image_count', 'done_at'])]
class GraphicOrder extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(GraphicOrderStatus::class, 'graphic_order_status_id');
    }
}
