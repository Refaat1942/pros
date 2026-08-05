<?php

namespace App\Support;

/** قوالب مجموعات الأطقم — التوصيف ↔ المعدلات. */
final class StockKitGroups
{
    /**
     * @return array<string, array{label: string, icon?: string, keywords?: list<string>, default_type?: string, fallback?: bool}>
     */
    public static function all(): array
    {
        return config('stock_kit_groups.groups', []);
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
     * @return list<array{key: string, label: string, icon: string, default_type: string}>
     */
    public static function forSelect(): array
    {
        return collect(self::all())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'icon' => $meta['icon'] ?? '📦',
                'default_type' => $meta['default_type'] ?? 'assembly',
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
        }

        return array_keys($labels);
    }
}
