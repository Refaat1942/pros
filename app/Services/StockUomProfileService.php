<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * قوالب تحويل وحدات التوريد — الافتراضي من config مع إمكانية تخصيص من الإدارة.
 */
class StockUomProfileService
{
    public const SETTING_KEY = 'stock_uom_profiles_custom';

    /** @return array<string, array{label: string, supply_uom: ?string, units_per_supply_unit: float|int, base_uom_hint?: string}> */
    public function allProfiles(): array
    {
        return Cache::rememberForever('stock_uom_profiles.merged', function () {
            $defaults = config('stock_uom_profiles.profiles', []);
            $custom = $this->customProfilesRaw();

            return array_merge($defaults, $custom);
        });
    }

    /** @return array<string, array<string, mixed>> */
    public function customProfilesOnly(): array
    {
        return $this->customProfilesRaw();
    }

    /** @return list<string> */
    public function supplyUnitSuggestions(): array
    {
        $fromSetting = $this->customSupplySuggestions();
        if ($fromSetting !== []) {
            return $fromSetting;
        }

        return config('stock_uom_profiles.supply_unit_suggestions', []);
    }

    /**
     * @param  array<string, array{label: string, supply_uom?: ?string, units_per_supply_unit: float|int|string, base_uom_hint?: string}>  $profiles
     * @param  list<string>|null  $supplySuggestions
     */
    public function save(array $profiles, ?array $supplySuggestions = null): void
    {
        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode([
                'profiles' => $profiles,
                'supply_unit_suggestions' => $supplySuggestions,
            ], JSON_UNESCAPED_UNICODE)],
        );

        Cache::forget('stock_uom_profiles.merged');
        Cache::forget('stock_uom_profiles.suggestions');
    }

    /** @return array<string, array<string, mixed>> */
    private function customProfilesRaw(): array
    {
        $payload = $this->storedPayload();

        return is_array($payload['profiles'] ?? null) ? $payload['profiles'] : [];
    }

    /** @return list<string> */
    private function customSupplySuggestions(): array
    {
        return Cache::rememberForever('stock_uom_profiles.suggestions', function () {
            $payload = $this->storedPayload();
            $list = $payload['supply_unit_suggestions'] ?? null;

            return is_array($list) ? array_values(array_filter(array_map('strval', $list))) : [];
        });
    }

    /** @return array<string, mixed> */
    private function storedPayload(): array
    {
        $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
