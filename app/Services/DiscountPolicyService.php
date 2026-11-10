<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Setting;

/**
 * سياسة الخصومات (يحددها السوبر أدمن من «إعدادات التكاليف»):
 *   - الفئات المسموح لها بالخصم: نقدي / جهات متعاقدة / عسكري.
 *   - أنواع الخصومات المعتمدة: الاسم، الطريقة (نسبة / مبلغ)، والحد الأقصى للنسبة.
 */
class DiscountPolicyService
{
    public const SETTING_KEY = 'discount_policy';

    public const PATHWAYS = [
        'cash' => 'المرضى النقدي (على نفقتهم)',
        'contracted' => 'الجهات المتعاقدة',
        'military' => 'الجهات العسكرية',
    ];

    private const DEFAULT_POLICY = [
        'pathways' => ['cash' => true, 'contracted' => true, 'military' => true],
        // زي حسبة اللائحة: خصم 15% وخصم 25% بنسبة ثابتة، وخصم إداري حر.
        'types' => [
            ['key' => 'regulation_15', 'label' => 'خصم 15%', 'fixed_percent' => 15],
            ['key' => 'regulation_25', 'label' => 'خصم 25%', 'fixed_percent' => 25],
            ['key' => 'administrative', 'label' => 'خصم إداري', 'allow_percent' => true, 'allow_amount' => true, 'max_percent' => null],
        ],
    ];

    /** @return array{pathways: array<string, bool>, types: list<array{key: string, label: string, allow_percent: bool, allow_amount: bool, max_percent: ?float, fixed_percent: ?float}>} */
    public function policy(): array
    {
        $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return $this->normalize(is_array($data) ? $data : self::DEFAULT_POLICY);
    }

    /** @param  array<string, mixed>  $data */
    public function save(array $data): array
    {
        $policy = $this->normalize($data);
        abort_if($policy['types'] === [], 422, 'أضف نوع خصم واحداً على الأقل.');

        $before = $this->policy();
        Setting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => json_encode($policy, JSON_UNESCAPED_UNICODE)]);

        AuditService::log(
            action: 'update',
            description: 'تحديث سياسة الخصومات',
            tag: 'admin',
            before: $before,
            after: $policy,
        );

        return $policy;
    }

    public function pathwayOf(CaseRecord $case): string
    {
        if ($case->isMilitary()) {
            return 'military';
        }

        return $case->isCashCivilian() ? 'cash' : 'contracted';
    }

    public function allowsCase(CaseRecord $case): bool
    {
        return (bool) ($this->policy()['pathways'][$this->pathwayOf($case)] ?? false);
    }

    /** @return array{key: string, label: string, allow_percent: bool, allow_amount: bool, max_percent: ?float}|null */
    public function findType(?string $key): ?array
    {
        foreach ($this->policy()['types'] as $type) {
            if ($type['key'] === $key) {
                return $type;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $data */
    private function normalize(array $data): array
    {
        $pathways = [];
        foreach (array_keys(self::PATHWAYS) as $key) {
            $pathways[$key] = (bool) ($data['pathways'][$key] ?? false);
        }

        $types = [];
        $seen = [];
        foreach ((array) ($data['types'] ?? []) as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = trim((string) ($row['key'] ?? '')) ?: 'type_'.($i + 1);
            $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($key)) ?: 'type_'.($i + 1);
            while (isset($seen[$key])) {
                $key .= '_'.($i + 1);
            }
            $seen[$key] = true;

            $allowPercent = (bool) ($row['allow_percent'] ?? true);
            $allowAmount = (bool) ($row['allow_amount'] ?? true);
            if (! $allowPercent && ! $allowAmount) {
                $allowPercent = true;
            }
            $max = $row['max_percent'] ?? null;
            $max = ($max === null || $max === '') ? null : round(min(99.99, max(0.01, (float) $max)), 2);
            // نسبة ثابتة (زي خصم 15% / 25% في حسبة اللائحة): نسبة بس، وبالقيمة دي بالظبط.
            $fixed = $row['fixed_percent'] ?? null;
            $fixed = ($fixed === null || $fixed === '') ? null : round(min(99.99, max(0.01, (float) $fixed)), 2);
            if ($fixed !== null) {
                $allowPercent = true;
                $allowAmount = false;
                $max = $fixed;
            }

            $types[] = [
                'key' => $key,
                'label' => mb_substr($label, 0, 100),
                'allow_percent' => $allowPercent,
                'allow_amount' => $allowAmount,
                'max_percent' => $max,
                'fixed_percent' => $fixed,
            ];
        }

        return ['pathways' => $pathways, 'types' => array_values($types)];
    }
}
