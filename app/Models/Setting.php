<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    protected const CACHE_KEY = 'settings.map';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key, or the given default when it is not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allValues()[$key] ?? $default;
    }

    /**
     * Upsert a setting value and forget the cached map.
     */
    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The full [key => value] map of settings, cached.
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return self::query()->pluck('value', 'key')->all();
        });
    }
}
