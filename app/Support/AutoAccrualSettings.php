<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class AutoAccrualSettings
{
    private const FILENAME = 'auto_accrual_settings.json';

    public static function get(): array
    {
        $defaults = self::defaults();
        $path = self::path();

        if (!File::exists($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    public static function save(array $settings): array
    {
        $merged = array_merge(self::defaults(), self::get(), $settings);
        $merged['enabled'] = (bool) ($merged['enabled'] ?? false);
        $merged['amount'] = self::trunc3((float) ($merged['amount'] ?? 1.250));
        $merged['run_time'] = '23:59';

        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $merged;
    }

    public static function nextRunAt(?Carbon $now = null): Carbon
    {
        $now = $now ? $now->copy() : now(config('app.timezone'));
        $candidate = $now->copy()->endOfMonth()->setTime(23, 59, 0);

        if ($now->greaterThanOrEqualTo($candidate)) {
            $candidate = $now->copy()->addMonthNoOverflow()->endOfMonth()->setTime(23, 59, 0);
        }

        return $candidate;
    }

    private static function defaults(): array
    {
        return [
            'enabled' => false,
            'amount' => 1.250,
            'run_time' => '23:59',
            'last_run_month' => null,
            'last_run_at' => null,
            'last_run_count' => 0,
            'last_run_message' => null,
        ];
    }

    private static function path(): string
    {
        return storage_path('app/' . self::FILENAME);
    }

    private static function trunc3(float $value): float
    {
        $multiplier = 1000;
        return $value >= 0
            ? floor($value * $multiplier) / $multiplier
            : ceil($value * $multiplier) / $multiplier;
    }
}
