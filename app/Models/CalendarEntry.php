<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'created_by_user_id', 'type', 'starts_on', 'ends_on', 'note'])]
class CalendarEntry extends Model
{
    public const TYPE_ABSENCE = 'absence';

    public const TYPE_MOBILE_OFFICE = 'mobile_office';

    public const TYPE_EXTERNAL_APPOINTMENT = 'external_appointment';

    public const TYPES = [self::TYPE_ABSENCE, self::TYPE_MOBILE_OFFICE, self::TYPE_EXTERNAL_APPOINTMENT];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('tenant');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MOBILE_OFFICE => __('Mobile-Office'),
            self::TYPE_EXTERNAL_APPOINTMENT => __('Auswärtstermin'),
            default => __('Abwesenheit'),
        };
    }

    public function dotClass(): string
    {
        return match ($this->type) {
            self::TYPE_MOBILE_OFFICE => 'bg-emerald-500',
            self::TYPE_EXTERNAL_APPOINTMENT => 'bg-violet-500',
            default => 'bg-amber-500',
        };
    }
}
