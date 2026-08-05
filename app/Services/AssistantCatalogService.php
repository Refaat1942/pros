<?php

namespace App\Services;

/**
 * يولّد مدخلات إرشادية تلقائية من config/dashboards.php
 * لأي صفحة ليس لها شرح مفصّل في knowledge.php.
 */
class AssistantCatalogService
{
    /** @var array<string, string> */
    private const DASHBOARD_LABELS = [
        'reception' => 'الاستقبال',
        'doctor' => 'الطبيب',
        'spec' => 'التوصيف',
        'adjustments' => 'المعدلات',
        'costing' => 'التكاليف',
        'operations' => 'التشغيل',
        'cashier' => 'الخزنة',
        'workshop' => 'الورشة',
        'technical' => 'المخزن',
        'admin' => 'الإدارة',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function pageEntries(): array
    {
        $entries = [];

        foreach (config('dashboards', []) as $dashboardKey => $dashboard) {
            if ($dashboardKey === 'home' || empty($dashboard['pages'])) {
                continue;
            }

            $dashLabel = self::DASHBOARD_LABELS[$dashboardKey]
                ?? ($dashboard['sidebar']['title'] ?? $dashboardKey);

            foreach ($dashboard['pages'] as $pageKey => $page) {
                if (! empty($page['hidden'])) {
                    continue;
                }

                $label = trim((string) ($page['label'] ?? $page['title'] ?? $pageKey));
                $title = trim((string) ($page['title'] ?? $label));
                $assistant = is_array($page['assistant'] ?? null) ? $page['assistant'] : [];

                $defaultAnswer = "صفحة «{$label}» في لوحة {$dashLabel}. افتحها من القائمة الجانبية. لو محتاج خطوات تفصيلية، اسأل باسم المهمة (مثلاً: صرف مواد، تسليم، عرض سعر).";
                $defaultSteps = [
                    "من القائمة الجانبية في لوحة {$dashLabel} اختار «{$label}».",
                    'نفّذ المطلوب في الشاشة ثم احفظ.',
                    'لو فيه زر طباعة 🖨️ — يطبع الورقة الرسمية.',
                ];

                $entries[] = [
                    'dashboard' => $dashboardKey,
                    'page' => $pageKey,
                    'keywords' => array_values(array_unique(array_merge(
                        $this->keywordsFor($dashboardKey, $pageKey, $label, $title),
                        array_map('strval', $assistant['keywords'] ?? []),
                    ))),
                    'title' => trim((string) ($assistant['title'] ?? $label)),
                    'answer' => trim((string) ($assistant['answer'] ?? $defaultAnswer)),
                    'steps' => array_values(array_filter(
                        array_map('strval', $assistant['steps'] ?? $defaultSteps),
                        fn (string $s) => trim($s) !== '',
                    )),
                    'catalog' => true,
                    'auto' => true,
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function keywordsFor(string $dashboard, string $page, string $label, string $title): array
    {
        $tokens = array_values(array_unique(array_filter([
            $dashboard,
            $page,
            $label,
            $title,
            str_replace('-', ' ', $page),
            str_replace('-', ' ', $label),
        ], fn (string $v) => trim($v) !== '')));

        return array_merge($tokens, [
            'ايه دي',
            'الصفحه دي',
            'الشاشه دي',
            'فين',
            'ازاي افتح',
        ]);
    }
}
