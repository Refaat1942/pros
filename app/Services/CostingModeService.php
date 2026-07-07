<?php

namespace App\Services;

use App\Models\CostingComponent;
use App\Models\CostingMode;
use Illuminate\Support\Facades\DB;

/**
 * أنماط التكاليف — طرف صناعي / صرف سريع — قابلة للتحكم من الإدارة.
 *
 * تُقرأ من قاعدة البيانات إن وُجدت، وإلا تُستخدم القيم الافتراضية المدمجة
 * (حتى تعمل الاختبارات والبيئات الجديدة دون بذور إلزامية).
 */
class CostingModeService
{
    public const MODE_LIMB = 'prosthetic_limb';

    public const MODE_QUICK = 'quick_dispense';

    /** القيم الافتراضية — أمثلة يمكن للإدارة تعديلها. */
    private const DEFAULTS = [
        [
            'key' => self::MODE_LIMB,
            'label' => 'طرف صناعي',
            'profit_rate' => 95.0,
            'has_components' => true,
            'components' => [
                ['label' => 'تكاليف الفحص الفني والمطابقة الحركية', 'rate' => 30.0],
                ['label' => 'تكاليف دمج المكونات والمفاصل الذكية', 'rate' => 25.0],
                ['label' => 'مصروفات إهلاك الآلات والمعدات', 'rate' => 23.0],
                ['label' => 'رسوم التقييم والتأهيل الحركي', 'rate' => 22.0],
            ],
        ],
        [
            'key' => self::MODE_QUICK,
            'label' => 'الصرف السريع',
            'profit_rate' => 40.0,
            'has_components' => false,
            'components' => [],
        ],
    ];

    /**
     * كل الأنماط النشطة — من قاعدة البيانات أو الافتراضيات.
     *
     * @return list<array{key:string, label:string, profit_rate:float, has_components:bool, components:list<array{label:string, rate:float}>}>
     */
    public function allModes(): array
    {
        $stored = CostingMode::query()
            ->where('active', true)
            ->with('components')
            ->orderBy('sort')
            ->get();

        if ($stored->isEmpty()) {
            return self::DEFAULTS;
        }

        return $stored->map(fn (CostingMode $mode) => [
            'key' => $mode->key,
            'label' => $mode->label,
            'profit_rate' => (float) $mode->profit_rate,
            'has_components' => (bool) $mode->has_components,
            'components' => $mode->components->map(fn (CostingComponent $c) => [
                'label' => $c->label,
                'rate' => (float) $c->rate,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * نمط واحد بمفتاحه — أو null.
     *
     * @return array{key:string, label:string, profit_rate:float, has_components:bool, components:list<array{label:string, rate:float}>}|null
     */
    public function find(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        foreach ($this->allModes() as $mode) {
            if ($mode['key'] === $key) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * حفظ الأنماط والمكوّنات (من شاشة الإدارة) — استبدال كامل.
     *
     * @param  list<array{key:string, label:string, profit_rate:float|int|string, has_components:bool, components:list<array{label:string, rate:float|int|string}>}>  $modes
     */
    public function saveModes(array $modes): void
    {
        DB::transaction(function () use ($modes) {
            $keptModeIds = [];

            foreach (array_values($modes) as $sort => $mode) {
                $key = trim((string) ($mode['key'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $record = CostingMode::updateOrCreate(
                    ['key' => $key],
                    [
                        'label' => trim((string) ($mode['label'] ?? $key)),
                        'profit_rate' => round((float) ($mode['profit_rate'] ?? 0), 2),
                        'has_components' => (bool) ($mode['has_components'] ?? false),
                        'active' => true,
                        'sort' => $sort,
                    ],
                );
                $keptModeIds[] = $record->id;

                $record->components()->delete();

                if ($record->has_components) {
                    foreach (array_values($mode['components'] ?? []) as $cSort => $component) {
                        $label = trim((string) ($component['label'] ?? ''));
                        if ($label === '') {
                            continue;
                        }
                        $record->components()->create([
                            'label' => $label,
                            'rate' => round((float) ($component['rate'] ?? 0), 2),
                            'sort' => $cSort,
                        ]);
                    }
                }
            }

            if ($keptModeIds !== []) {
                CostingMode::whereNotIn('id', $keptModeIds)->delete();
            }
        });
    }
}
