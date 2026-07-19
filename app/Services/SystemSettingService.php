<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember('setting:'.$key, 300, fn () => SystemSetting::query()->where('key', $key)->first());
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            default => $setting->value,
        };
    }

    public function set(string $key, mixed $value, string $type, bool $public, ?int $actorId): SystemSetting
    {
        $setting = SystemSetting::updateOrCreate(['key' => $key], [
            'value' => $type === 'boolean' ? ($value ? '1' : '0') : (string) $value,
            'type' => $type, 'is_public' => $public, 'updated_by' => $actorId,
        ]);
        Cache::forget('setting:'.$key);

        return $setting;
    }
}
