<?php

namespace App\Support;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class Money
{
    public static function format(float|int|string|null $amount): string
    {
        $currency = Cache::remember(SiteSettingController::CURRENCY_CACHE_KEY, 60, function () {
            $value = SiteSetting::where('key', 'currency')->first()?->value ?? [];

            return array_merge(SettingsController::DEFAULTS['currency'], $value);
        });

        $formatted = number_format((float) $amount, 2);
        $symbol = $currency['symbol'] ?? 'LE';

        return ($currency['position'] ?? 'before') === 'after'
            ? "{$formatted} {$symbol}"
            : "{$symbol} {$formatted}";
    }
}
