<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'team_id'];

    protected $casts = ['value' => 'array'];

    public static function read(string $key, array $default): array
    {
        try {
            $value = static::where('key', $key)->value('value');
        } catch (\Throwable $e) {
            return $default;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : $default;
    }

    public static function write(string $key, array $value, ?string $teamId = null): array
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'team_id' => $teamId]);

        return $value;
    }
}
