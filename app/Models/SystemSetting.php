<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function value(string $key, array $default = []): array
    {
        $setting = static::query()->where('key', $key)->first();

        return is_array($setting?->value) ? $setting->value : $default;
    }

    public static function putValue(string $key, array $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => array_values($value)],
        );
    }
}
