<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public const KEY_TECHNICAL_CHECK = 'technical_check_rate';

    public const KEY_COMPONENTS_INTEGRATION = 'components_integration_rate';

    public const KEY_MACHINERY_DEPRECIATION = 'machinery_depreciation_rate';

    public const KEY_REHABILITATION_ASSESSMENT = 'rehabilitation_assessment_rate';

    /** @var array<string, float> */
    private const DEFAULT_OVERHEAD_RATES = [
        self::KEY_TECHNICAL_CHECK => 30.0,
        self::KEY_COMPONENTS_INTEGRATION => 25.0,
        self::KEY_MACHINERY_DEPRECIATION => 23.0,
        self::KEY_REHABILITATION_ASSESSMENT => 22.0,
    ];

    /** @return array<string, float> */
    public function overheadRates(): array
    {
        return Cache::rememberForever('settings.overhead_rates', function () {
            $stored = Setting::query()
                ->whereIn('key', array_keys(self::DEFAULT_OVERHEAD_RATES))
                ->pluck('value', 'key');

            $rates = [];

            foreach (self::DEFAULT_OVERHEAD_RATES as $key => $default) {
                $rates[$key] = round((float) ($stored[$key] ?? $default), 2);
            }

            return $rates;
        });
    }

    /** @param array<string, float|int|string> $rates */
    public function updateOverheadRates(array $rates): void
    {
        foreach (self::DEFAULT_OVERHEAD_RATES as $key => $default) {
            if (! array_key_exists($key, $rates)) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) round((float) $rates[$key], 2)],
            );
        }

        Cache::forget('settings.overhead_rates');
    }

    public function overheadRatesSum(): float
    {
        return round(array_sum($this->overheadRates()), 2);
    }

    /** @return list<array{key: string, label: string, rate: float}> */
    public function overheadRateDefinitions(): array
    {
        $rates = $this->overheadRates();

        return [
            [
                'key' => self::KEY_TECHNICAL_CHECK,
                'label' => 'تكاليف الفحص الفني والمطابقة الحركية',
                'rate' => $rates[self::KEY_TECHNICAL_CHECK],
            ],
            [
                'key' => self::KEY_COMPONENTS_INTEGRATION,
                'label' => 'تكاليف دمج المكونات والمفاصل الذكية',
                'rate' => $rates[self::KEY_COMPONENTS_INTEGRATION],
            ],
            [
                'key' => self::KEY_MACHINERY_DEPRECIATION,
                'label' => 'مصروفات إهلاك الآلات والمعدات',
                'rate' => $rates[self::KEY_MACHINERY_DEPRECIATION],
            ],
            [
                'key' => self::KEY_REHABILITATION_ASSESSMENT,
                'label' => 'رسوم التقييم والتأهيل الحركي',
                'rate' => $rates[self::KEY_REHABILITATION_ASSESSMENT],
            ],
        ];
    }
}
