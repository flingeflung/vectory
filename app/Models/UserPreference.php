<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'key', 'config'])]
class UserPreference extends Model
{
    public const DASHBOARD = 'dashboard';

    public const CALENDAR = 'calendar';

    public const PEOPLE_TABLE = 'people_table';

    public const PLANNING = 'planning';

    public const PROJECT_PLANNING = 'project_planning';

    public const PRESET_COPY = 'preset_copy';

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function configFor(int $userId, string $key): array
    {
        return static::query()->where('user_id', $userId)->where('key', $key)->value('config') ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function persist(int $userId, string $key, array $config): void
    {
        static::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['config' => $config],
        );
    }
}
