<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\StockKit;
use Illuminate\Support\Facades\Cache;

/** قوالب مجموعات الأطقم — التوصيف ↔ المعدلات. */
final class StockKitGroups
{
    public const SETTING_KEY = 'stock_kit_groups_json';

    /**
     * @return array<string, array{label: string, icon?: string, keywords?: list<string>, default_type?: string, fallback?: bool}>
     */
    public static function all(): array
    {
        return Cache::rememberForever('stock_kit_groups.all', function () {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && $decoded !== []) {
                    return $decoded;
                }
            }

            return config('stock_kit_groups.groups', []);
        });
    }

    /**
     * @param  array<string, array{label: string, icon?: string, keywords?: list<string>, default_type?: string, fallback?: bool}>  $groups
     */
    public static function saveAll(array $groups): void
    {
        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($groups, JSON_UNESCAPED_UNICODE)],
        );
        Cache::forget('stock_kit_groups.all');
    }

    public static function deleteGroup(string $key): void
    {
        $key = trim($key);
        if ($key === '' || ! isset(self::all()[$key])) {
            return;
        }

        if (StockKit::query()->where('spec_group', $key)->exists()) {
            abort(422, 'لا يمكن حذف المجموعة — مرتبطة بأطقم موجودة.');
        }

        $groups = self::all();
        unset($groups[$key]);
        self::saveAll($groups);
    }

    /**
     * @param  array{label: string, icon?: string, keywords?: list<string>, default_type?: string}  $meta
     */
    public static function upsertGroup(string $key, array $meta, ?string $previousKey = null): void
    {
        $key = self::sanitizeKey($key);
        if ($key === '') {
            abort(422, 'مفتاح المجموعة غير صالح.');
        }

        $groups = self::all();
        if ($previousKey !== null && $previousKey !== $key && isset($groups[$previousKey])) {
            if (StockKit::query()->where('spec_group', $previousKey)->exists()) {
                StockKit::query()->where('spec_group', $previousKey)->update(['spec_group' => $key]);
            }
            unset($groups[$previousKey]);
        }

        $groups[$key] = [
            'label' => trim($meta['label'] ?? ''),
            'icon' => trim($meta['icon'] ?? '📦') ?: '📦',
            'keywords' => array_values(array_filter(array_map('trim', $meta['keywords'] ?? []))),
            'default_type' => in_array($meta['default_type'] ?? '', ['assembly', 'accessory'], true)
                ? $meta['default_type']
                : 'assembly',
        ];

        if ($groups[$key]['label'] === '') {
            abort(422, 'اسم المجموعة مطلوب.');
        }

        self::saveAll($groups);
    }

    public static function label(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::all()[$key]['label'] ?? null;
    }

    public static function normalizeKey(?string $key): ?string
    {
        $key = trim((string) ($key ?? ''));

        return $key !== '' && isset(self::all()[$key]) ? $key : null;
    }

    /**
     * @return list<array{key: string, label: string, icon: string, default_type: string, keywords: list<string>}>
     */
    public static function forSelect(): array
    {
        return collect(self::all())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'icon' => $meta['icon'] ?? '📦',
                'default_type' => $meta['default_type'] ?? 'assembly',
                'keywords' => array_values($meta['keywords'] ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, keywords: list<string>}>
     */
    public static function forClientMatcher(): array
    {
        return collect(self::all())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'keywords' => array_values($meta['keywords'] ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string> تسميات المجموعات النشطة من نص أو group_label
     */
    public static function matchLabelsFromText(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }

        $labels = [];
        foreach (self::all() as $meta) {
            $label = $meta['label'] ?? '';
            foreach ($meta['keywords'] ?? [] as $keyword) {
                if ($keyword !== '' && mb_stripos($text, mb_strtolower($keyword)) !== false) {
                    $labels[$label] = true;
                    break;
                }
            }
            if ($label !== '' && mb_stripos($text, mb_strtolower($label)) !== false) {
                $labels[$label] = true;
            }
        }

        return array_keys($labels);
    }

    private static function sanitizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_\-]/', '_', $key) ?? '';

        return trim($key, '_');
    }
}
