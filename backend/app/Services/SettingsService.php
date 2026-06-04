<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime settings with config/regal.php as the factory fallback.
 * DB value (settings table) always wins over the config default.
 */
class SettingsService
{
    protected const CACHE_KEY = 'regal.settings';

    /** Map of setting key => [config path, type] used for defaults + casting. */
    public static function map(): array
    {
        return [
            'roi_daily_percent'      => ['regal.roi.daily_percent', 'number'],
            'roi_return_multiple'    => ['regal.roi.return_multiple', 'number'],
            'min_deposit'            => ['regal.limits.min_deposit', 'number'],
            'min_withdrawal'         => ['regal.limits.min_withdrawal', 'number'],
            'max_withdrawal_daily'   => ['regal.limits.max_withdrawal_daily', 'number'],
            'min_transfer'           => ['regal.limits.min_transfer', 'number'],
            'min_reinvest'           => ['regal.limits.min_reinvest', 'number'],
            'withdrawal_fee_percent' => ['regal.withdrawal.fee_percent', 'number'],
            'sponsor_bonus_percents' => ['regal.sponsor_bonus_percents', 'json'],
            'match_percents'         => ['regal.match_percents', 'json'],
            'maintenance_start'      => ['regal.maintenance.start', 'string'],
            'maintenance_end'        => ['regal.maintenance.end', 'string'],
            'maintenance_manual'     => [null, 'bool'], // admin override toggle, default false
            'deposit_address'        => ['regal.deposit_address', 'string'],
            'deposit_network'        => ['regal.deposit_network', 'string'],
        ];
    }

    protected function db(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }

    public function get(string $key, $default = null)
    {
        $map  = self::map();
        $type = $map[$key][1] ?? 'string';
        $db   = $this->db();

        if (array_key_exists($key, $db) && $db[$key] !== null) {
            return $this->castOut($db[$key], $type);
        }

        // Fallback to config default
        $configPath = $map[$key][0] ?? null;
        if ($configPath) {
            return config($configPath, $default);
        }

        return $default;
    }

    public function set(string $key, $value, ?string $type = null): void
    {
        $type ??= self::map()[$key][1] ?? 'string';
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->castIn($value, $type), 'type' => $type]
        );
        Cache::forget(self::CACHE_KEY);
    }

    /** Full settings snapshot (defaults merged with DB overrides) for the admin UI. */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::map()) as $key) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    protected function castOut($value, string $type)
    {
        return match ($type) {
            'json'   => is_array($value) ? $value : json_decode($value, true),
            'number' => $value + 0,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default  => $value,
        };
    }

    protected function castIn($value, string $type): string
    {
        return match ($type) {
            'json'  => is_string($value) ? $value : json_encode($value),
            'bool'  => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
