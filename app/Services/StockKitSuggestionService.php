<?php

namespace App\Services;

use App\Models\AdjustmentItemGroup;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\StockItem;
use App\Models\StockKit;
use App\Models\StockKitSuggestionDismissal;
use App\Models\User;
use App\Support\StockKitGroups;

/**
 * التجميع التلقائي (Auto-grouping) — بجانب التجميع اليدوي الموجود.
 *
 * يحلل قوائم المواد (BOM) للحالات المسجلة ويكتشف الخامات التي استُخدمت معاً
 * في أكثر من حالة، ثم يقترحها على المستخدم: «اتعملت كذا مرة بالخامات دي — نعملها مجموعة؟»
 *
 * نطاقان:
 *  - SCOPE_ADJUSTMENTS: بنود مكتب المعدلات فقط ← «مجموعات المعدلات» (كميات عشرية).
 *  - SCOPE_KITS:        كل بنود BOM ← «أطقم جاهزة ومخصصات» في الإدارة (كميات صحيحة).
 *
 *  - نعم → تُفتح نافذة الإنشاء اليدوية معبأة بالمكوّنات والكميات المعتادة للمراجعة قبل الحفظ.
 *  - لا  → تُسجَّل في stock_kit_suggestion_dismissals (لكل نطاق) ولا تُقترح ثانية.
 *
 * الخوارزمية: مجموعات عناصر متكررة (Eclat عبر تقاطع أرقام الحالات) ثم الإبقاء على
 * «المغلقة» فقط — أكبر تركيبة لكل نفس مجموعة الحالات — لتجنب تكرار نفس الاقتراح بأجزائه.
 */
class StockKitSuggestionService
{
    public const SCOPE_KITS = 'kits';

    public const SCOPE_ADJUSTMENTS = 'adjustments';

    /** «اتعمل أكتر من مرة» */
    public const MIN_CASES = 2;

    private const MAX_SET_SIZE = 8;

    private const MAX_BOMS = 3000;

    private const MAX_ITEMSETS_PER_LEVEL = 20000;

    private const MAX_SUGGESTIONS = 12;

    /**
     * @return list<array{
     *     signature: string,
     *     case_count: int,
     *     last_used_at: ?string,
     *     suggested_name: string,
     *     spec_group: ?string,
     *     spec_group_label: ?string,
     *     items: list<array{stock_item_id: int, code: string, name: string, uom: string, page_number: string, qty: float|int}>
     * }>
     */
    public function suggestions(string $scope = self::SCOPE_KITS, int $minCases = self::MIN_CASES): array
    {
        $scope = $this->normalizeScope($scope);
        $minCases = max(2, $minCases);
        $transactions = $this->transactions($scope);

        if (count($transactions) < $minCases) {
            return [];
        }

        $closed = $this->closedItemsets($transactions, $minCases);

        $existingSets = $scope === self::SCOPE_ADJUSTMENTS
            ? $this->existingAdjustmentGroupItemSets()
            : $this->existingKitItemSets();

        $dismissed = StockKitSuggestionDismissal::query()
            ->where('scope', $scope)
            ->pluck('signature')
            ->flip();

        usort($closed, fn (array $a, array $b) => [count($b['tids']), count($b['codes'])] <=> [count($a['tids']), count($a['codes'])]);

        $result = [];

        foreach ($closed as $set) {
            $signature = self::signature($set['codes']);
            if (isset($dismissed[$signature])) {
                continue;
            }

            $row = $this->buildSuggestion($scope, $set['codes'], $set['tids'], $transactions, $signature);
            if ($row === null || $this->coveredByExisting(array_column($row['items'], 'stock_item_id'), $existingSets)) {
                continue;
            }

            $result[] = $row;

            if (count($result) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $codes
     */
    public function dismiss(string $scope, array $codes, ?User $user, int $caseCount = 0): void
    {
        $scope = $this->normalizeScope($scope);
        $codes = self::normalizeCodes($codes);

        if (count($codes) < 2) {
            abort(422, 'الاقتراح يجب أن يحتوي صنفين على الأقل.');
        }

        StockKitSuggestionDismissal::query()->updateOrCreate(
            ['scope' => $scope, 'signature' => self::signature($codes)],
            [
                'item_codes' => $codes,
                'case_count' => max(0, $caseCount),
                'dismissed_by_user_id' => $user?->id,
            ],
        );

        AuditService::log(
            action: 'reject',
            description: 'رفض اقتراح تجميع تلقائي — '.implode(' + ', $codes),
            tag: $scope === self::SCOPE_ADJUSTMENTS ? 'adjustments' : 'admin',
            after: ['scope' => $scope, 'codes' => $codes, 'case_count' => $caseCount],
        );
    }

    /** @param  list<string>  $codes */
    public static function signature(array $codes): string
    {
        return sha1(implode('|', self::normalizeCodes($codes)));
    }

    private function normalizeScope(string $scope): string
    {
        return $scope === self::SCOPE_ADJUSTMENTS ? self::SCOPE_ADJUSTMENTS : self::SCOPE_KITS;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private static function normalizeCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn ($c) => trim((string) $c), $codes),
            static fn (string $c) => $c !== '',
        )));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * BOM لكل حالة: الكود => [الكمية، المجموعة] — يُستبعد ما فيه أقل من صنفين.
     *
     * @return array<int, array{codes: array<string, array{qty: float, group: string}>, at: ?string}>
     */
    private function transactions(string $scope): array
    {
        $boms = Bom::query()
            ->whereNotNull('case_id')
            ->with(['items' => function ($q) use ($scope) {
                $q->select(['id', 'bom_id', 'stock_item_code', 'qty', 'group_label', 'source']);
                if ($scope === self::SCOPE_ADJUSTMENTS) {
                    $q->where('source', BomItem::SOURCE_ADJUSTMENT);
                }
            }])
            ->latest('id')
            ->limit(self::MAX_BOMS)
            ->get(['id', 'case_id', 'created_at']);

        $transactions = [];

        foreach ($boms as $bom) {
            $codes = [];
            foreach ($bom->items as $item) {
                $code = trim((string) $item->stock_item_code);
                if ($code === '') {
                    continue;
                }

                $codes[$code] ??= ['qty' => 0.0, 'group' => ''];
                $codes[$code]['qty'] += (float) $item->qty;
                if ($codes[$code]['group'] === '' && trim((string) $item->group_label) !== '') {
                    $codes[$code]['group'] = trim((string) $item->group_label);
                }
            }

            if (count($codes) < 2) {
                continue;
            }

            // حالة واحدة = معاملة واحدة حتى لو تكررت BOM لنفس الحالة.
            $transactions[(int) $bom->case_id] ??= [
                'codes' => $codes,
                'at' => $bom->created_at?->toDateString(),
            ];
        }

        return $transactions;
    }

    /**
     * مجموعات العناصر المتكررة المغلقة (حجم ≥ 2).
     *
     * @param  array<int, array{codes: array<string, mixed>, at: ?string}>  $transactions
     * @return list<array{codes: list<string>, tids: list<int>}>
     */
    private function closedItemsets(array $transactions, int $minCases): array
    {
        $tidsByCode = [];
        foreach ($transactions as $tid => $tx) {
            foreach (array_keys($tx['codes']) as $code) {
                $tidsByCode[(string) $code][] = $tid;
            }
        }

        $level = [];
        foreach ($tidsByCode as $code => $tids) {
            if (count($tids) >= $minCases) {
                $level[] = ['codes' => [(string) $code], 'tids' => $tids];
            }
        }

        usort($level, fn ($a, $b) => strcmp($a['codes'][0], $b['codes'][0]));

        $frequent = [];

        for ($size = 2; $size <= self::MAX_SET_SIZE && count($level) > 1; $size++) {
            $byPrefix = [];
            foreach ($level as $set) {
                $byPrefix[implode("\x1F", array_slice($set['codes'], 0, $size - 2))][] = $set;
            }

            $next = [];
            foreach ($byPrefix as $group) {
                $n = count($group);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $tids = array_values(array_intersect($group[$i]['tids'], $group[$j]['tids']));
                        if (count($tids) < $minCases) {
                            continue;
                        }

                        $next[] = [
                            'codes' => [...$group[$i]['codes'], $group[$j]['codes'][$size - 2]],
                            'tids' => $tids,
                        ];

                        if (count($next) >= self::MAX_ITEMSETS_PER_LEVEL) {
                            break 3;
                        }
                    }
                }
            }

            array_push($frequent, ...$next);
            $level = $next;
        }

        // «مغلقة»: لكل مجموعة حالات متطابقة نُبقي أكبر تركيبة فقط.
        $closedByTids = [];
        foreach ($frequent as $set) {
            $key = implode(',', $set['tids']);
            if (! isset($closedByTids[$key]) || count($set['codes']) > count($closedByTids[$key]['codes'])) {
                $closedByTids[$key] = $set;
            }
        }

        return array_values($closedByTids);
    }

    /** @return list<list<int>> */
    private function existingKitItemSets(): array
    {
        return StockKit::query()
            ->where('is_active', true)
            ->with('items:id,stock_kit_id,stock_item_id')
            ->get()
            ->map(fn (StockKit $kit) => $kit->items->pluck('stock_item_id')->map(fn ($id) => (int) $id)->all())
            ->filter(fn (array $ids) => $ids !== [])
            ->values()
            ->all();
    }

    /** @return list<list<int>> */
    private function existingAdjustmentGroupItemSets(): array
    {
        return AdjustmentItemGroup::query()
            ->with('lines:id,adjustment_item_group_id,stock_item_code')
            ->get()
            ->map(fn (AdjustmentItemGroup $group) => $group->lines
                ->map(fn ($line) => StockItem::findByOperationalCode((string) $line->stock_item_code)?->id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all())
            ->filter(fn (array $ids) => $ids !== [])
            ->values()
            ->all();
    }

    /**
     * الاقتراح مُغطّى إذا كانت كل أصنافه موجودة في مجموعة/طقم قائم بالفعل.
     *
     * @param  list<int>  $itemIds
     * @param  list<list<int>>  $existingSets
     */
    private function coveredByExisting(array $itemIds, array $existingSets): bool
    {
        foreach ($existingSets as $ids) {
            if (array_diff($itemIds, $ids) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<int>  $tids
     * @param  array<int, array{codes: array<string, array{qty: float, group: string}>, at: ?string}>  $transactions
     * @return array<string, mixed>|null
     */
    private function buildSuggestion(string $scope, array $codes, array $tids, array $transactions, string $signature): ?array
    {
        $items = [];
        $groupVotes = [];
        $lastUsed = null;

        foreach ($tids as $tid) {
            $at = $transactions[$tid]['at'] ?? null;
            if ($at !== null && ($lastUsed === null || $at > $lastUsed)) {
                $lastUsed = $at;
            }
        }

        foreach ($codes as $code) {
            $stockItem = StockItem::findByOperationalCode($code);

            if ($stockItem === null) {
                return null;
            }

            $qtyVotes = [];
            foreach ($tids as $tid) {
                $line = $transactions[$tid]['codes'][$code];
                $qty = $scope === self::SCOPE_ADJUSTMENTS
                    ? max(0.001, round($line['qty'], 3))
                    : max(1, (int) round($line['qty']));
                $qtyVotes[(string) $qty] = ($qtyVotes[(string) $qty] ?? 0) + 1;

                if ($line['group'] !== '') {
                    $groupVotes[$line['group']] = ($groupVotes[$line['group']] ?? 0) + 1;
                }
            }
            arsort($qtyVotes);
            $typicalQty = (string) array_key_first($qtyVotes);

            $items[] = [
                'stock_item_id' => (int) $stockItem->id,
                'code' => $stockItem->pickerCode(),
                'name' => (string) $stockItem->name,
                'uom' => (string) ($stockItem->uom ?: 'قطعة'),
                'page_number' => (string) ($stockItem->page_number ?? ''),
                'qty' => $scope === self::SCOPE_ADJUSTMENTS ? (float) $typicalQty : (int) $typicalQty,
            ];
        }

        arsort($groupVotes);
        [$specGroup, $specGroupLabel] = $this->resolveSpecGroup((string) (array_key_first($groupVotes) ?? ''));

        $names = array_column($items, 'name');
        $suggestedName = implode(' + ', array_slice($names, 0, 3)).(count($names) > 3 ? ' + '.(count($names) - 3).' أخرى' : '');

        return [
            'signature' => $signature,
            'case_count' => count($tids),
            'last_used_at' => $lastUsed,
            // اسم مجموعات المعدلات ≤ 120 حرفاً (فريد).
            'suggested_name' => mb_substr($suggestedName, 0, 110),
            'spec_group' => $specGroup,
            'spec_group_label' => $specGroupLabel,
            'items' => $items,
        ];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveSpecGroup(string $groupLabel): array
    {
        if ($groupLabel === '') {
            return [null, null];
        }

        $groups = StockKitGroups::all();

        foreach ($groups as $key => $meta) {
            if (trim((string) ($meta['label'] ?? '')) === $groupLabel) {
                return [(string) $key, (string) $meta['label']];
            }
        }

        $matched = StockKitGroups::matchLabelsFromText($groupLabel)[0] ?? null;
        foreach ($groups as $key => $meta) {
            if ($matched !== null && ($meta['label'] ?? null) === $matched) {
                return [(string) $key, $matched];
            }
        }

        return [null, $groupLabel];
    }
}
