<?php

namespace App\Services;

use App\Models\User;

/**
 * مساعد ذكي إرشادي أوفلاين — بحث بالكلمات المفتاحية داخل قاعدة معرفة
 * ثابتة بالعامية المصرية، مقيَّد بصلاحيات المستخدم واللوحة/الصفحة الحالية.
 */
class AssistantService
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $knowledge = null;

    /** @var list<string> */
    private const DIAGRAM_QUERY_TOKENS = [
        'رسم', 'ارسم', 'بالرسم', 'مخطط', 'diagram', 'flow', 'flowchart',
        'chart', 'خريطه', 'خريطة', 'مسار مرسوم', 'flow chart',
    ];

    public function __construct(
        private readonly AssistantCatalogService $catalog,
    ) {}

    /**
     * مخطط مسار الحالة الافتراضي — يُعرض عند طلب «بالرسم».
     *
     * @return list<array{label: string, sub?: string, branch?: string}>
     */
    public static function defaultWorkflowDiagram(): array
    {
        return [
            ['label' => 'استقبال', 'sub' => 'تسجيل + QR'],
            ['label' => 'طبيب', 'sub' => 'كشف'],
            ['label' => 'توصيف', 'sub' => 'أكواد'],
            ['label' => 'معدلات', 'sub' => 'تجربة'],
            ['label' => 'تكاليف', 'sub' => 'تسعير'],
            ['label' => 'عرض سعر', 'sub' => 'مدني', 'branch' => 'مدني'],
            ['label' => 'موافقة جهة', 'sub' => 'خطاب', 'branch' => 'مدني'],
            ['label' => 'تشغيل', 'sub' => 'أمر شغل'],
            ['label' => 'مخزن', 'sub' => 'باركود'],
            ['label' => 'ورشة', 'sub' => 'تصنيع'],
            ['label' => 'تسليم', 'sub' => 'QR + إغلاق'],
        ];
    }

    /**
     * مخطط مسار عسكري مبسّط.
     *
     * @return list<array{label: string, sub?: string}>
     */
    public static function militaryWorkflowDiagram(): array
    {
        return [
            ['label' => 'استقبال', 'sub' => 'عسكري'],
            ['label' => 'طبيب', 'sub' => 'كشف'],
            ['label' => 'توصيف → معدلات → تكاليف', 'sub' => 'بدون عرض سعر'],
            ['label' => 'تصديق خدمات', 'sub' => 'ضباط/عائلات'],
            ['label' => 'تشغيل', 'sub' => 'أمر شغل'],
            ['label' => 'مخزن → ورشة → تسليم', 'sub' => 'تكلفة سيادية'],
        ];
    }

    /**
     * اقتراحات سياقية للوحة/الصفحة الحالية + مقدمة عامة.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestions(User $user, ?string $dashboard, ?string $page, int $limit = 6): array
    {
        $accessible = $this->accessibleEntries($user);

        $contextual = array_values(array_filter(
            $accessible,
            fn (array $entry) => $this->matchesContext($entry, $dashboard, $page)
        ));

        $general = array_values(array_filter(
            $accessible,
            fn (array $entry) => ($entry['dashboard'] ?? '*') === '*'
        ));

        $merged = $this->uniqueEntries(array_merge($contextual, $general));

        return array_map(
            fn (array $entry) => $this->present($entry, false, null),
            array_slice($merged, 0, $limit)
        );
    }

    /**
     * بحث بالكلمات المفتاحية، مع أولوية لعناصر اللوحة/الصفحة الحالية.
     *
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $query, ?string $dashboard, ?string $page, int $limit = 8): array
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return $this->suggestions($user, $dashboard, $page, $limit);
        }

        $tokens = array_values(array_filter(
            explode(' ', $normalizedQuery),
            fn (string $token) => mb_strlen($token) >= 2
        ));

        if ($tokens === []) {
            $tokens = [$normalizedQuery];
        }

        $scored = [];
        $wantsDiagram = $this->wantsDiagram($normalizedQuery, $tokens);

        foreach ($this->accessibleEntries($user) as $index => $entry) {
            $score = $this->scoreEntry($entry, $normalizedQuery, $tokens);

            if ($score <= 0 && ! $wantsDiagram) {
                continue;
            }

            if ($wantsDiagram && ! empty($entry['diagram'])) {
                $score += 8;
            }

            if ($this->matchesContext($entry, $dashboard, $page)) {
                $score += 3;
            }

            if ($score <= 0) {
                continue;
            }

            $scored[] = ['score' => $score, 'order' => $index, 'entry' => $entry];
        }

        usort($scored, function (array $a, array $b) {
            return $b['score'] <=> $a['score'] ?: $a['order'] <=> $b['order'];
        });

        $results = array_map(
            fn (array $row) => $this->present($row['entry'], $wantsDiagram, $normalizedQuery),
            array_slice($scored, 0, $limit)
        );

        if ($results === [] && $wantsDiagram) {
            return [$this->presentDiagramFallback($normalizedQuery)];
        }

        return array_values($results);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accessibleEntries(User $user): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $entry) => $this->canSee($user, $entry)
        ));
    }

    private function canSee(User $user, array $entry): bool
    {
        $dashboard = $entry['dashboard'] ?? '*';

        if ($dashboard === '*') {
            return true;
        }

        $page = $entry['page'] ?? '*';

        if ($page === '*') {
            return $user->canAccessDashboard($dashboard);
        }

        if ($user->canViewDashboardPage($dashboard, $page)) {
            return true;
        }

        // لو يقدر يفتح اللوحة، اسمح بمحتواها الإرشادي العام.
        return $user->canAccessDashboard($dashboard);
    }

    private function matchesContext(array $entry, ?string $dashboard, ?string $page): bool
    {
        if ($dashboard === null || $dashboard === '') {
            return false;
        }

        if (($entry['dashboard'] ?? '*') !== $dashboard) {
            return false;
        }

        $entryPage = $entry['page'] ?? '*';

        if ($entryPage === '*') {
            return true;
        }

        return $page !== null && $page !== '' && $entryPage === $page;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function scoreEntry(array $entry, string $normalizedQuery, array $tokens): int
    {
        $keywords = array_map([$this, 'normalize'], $entry['keywords'] ?? []);
        $title = $this->normalize($entry['title'] ?? '');
        $answer = $this->normalize($entry['answer'] ?? '');
        $haystack = $title.' '.$answer.' '.implode(' ', $keywords);

        $score = 0;

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($normalizedQuery, $keyword)) {
                $score += 5;
            }
        }

        foreach ($tokens as $token) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($keyword, $token)) {
                    $score += 3;

                    continue 2;
                }
            }

            if (str_contains($title, $token)) {
                $score += 2;

                continue;
            }

            if (str_contains($haystack, $token)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function uniqueEntries(array $entries): array
    {
        $seen = [];
        $unique = [];

        foreach ($entries as $entry) {
            $key = ($entry['dashboard'] ?? '*').'|'.($entry['page'] ?? '*').'|'.($entry['title'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(array $entry, bool $wantsDiagram = false, ?string $normalizedQuery = null): array
    {
        $diagram = $entry['diagram'] ?? null;

        if ($wantsDiagram && empty($diagram)) {
            if ($this->isMilitaryQuery($normalizedQuery)) {
                $diagram = self::militaryWorkflowDiagram();
            } elseif ($this->isWorkflowEntry($entry)) {
                $diagram = self::defaultWorkflowDiagram();
            }
        }

        return [
            'title' => $entry['title'] ?? '',
            'answer' => $entry['answer'] ?? '',
            'steps' => array_values($entry['steps'] ?? []),
            'diagram' => is_array($diagram) ? array_values($diagram) : null,
            'dashboard' => $entry['dashboard'] ?? '*',
            'page' => $entry['page'] ?? '*',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDiagramFallback(?string $normalizedQuery): array
    {
        $military = $this->isMilitaryQuery($normalizedQuery);

        return [
            'title' => $military ? 'مخطط المسار العسكري' : 'مخطط مسار الحالة (مدني)',
            'answer' => $military
                ? 'ده مخطط مبسّط للمسار العسكري — من التسجيل لحد التسليم بدون عرض سعر مدني.'
                : 'ده مخطط مبسّط لمسار الحالة المدنية — من الاستقبال لحد التسليم.',
            'steps' => [],
            'diagram' => $military ? self::militaryWorkflowDiagram() : self::defaultWorkflowDiagram(),
            'dashboard' => '*',
            'page' => '*',
        ];
    }

    private function wantsDiagram(string $normalizedQuery, array $tokens): bool
    {
        foreach (self::DIAGRAM_QUERY_TOKENS as $token) {
            if (str_contains($normalizedQuery, $this->normalize($token))) {
                return true;
            }
        }

        foreach ($tokens as $token) {
            if (in_array($token, ['رسم', 'ارسم', 'مخطط', 'diagram', 'flow', 'chart'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isMilitaryQuery(?string $normalizedQuery): bool
    {
        if ($normalizedQuery === null || $normalizedQuery === '') {
            return false;
        }

        foreach (['عسكري', 'عسكريه', 'جيش', 'ضابط', 'ضباط', 'services'] as $token) {
            if (str_contains($normalizedQuery, $this->normalize($token))) {
                return true;
            }
        }

        return false;
    }

    private function isWorkflowEntry(array $entry): bool
    {
        $keywords = $entry['keywords'] ?? [];

        return in_array('workflow', $keywords, true)
            || in_array('مسار', $keywords, true)
            || ($entry['dashboard'] ?? '') === '*'
            && str_contains($this->normalize($entry['title'] ?? ''), 'تمشي');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function all(): array
    {
        if ($this->knowledge === null) {
            $path = resource_path('assistant/knowledge.php');
            $data = is_file($path) ? require $path : [];
            $static = is_array($data) ? $data : [];
            $this->knowledge = $this->mergeKnowledge($static, $this->catalog->pageEntries());
        }

        return $this->knowledge;
    }

    /**
     * @param  list<array<string, mixed>>  $static
     * @param  list<array<string, mixed>>  $catalog
     * @return list<array<string, mixed>>
     */
    private function mergeKnowledge(array $static, array $catalog): array
    {
        $keys = [];
        foreach ($static as $entry) {
            $keys[$this->entryKey($entry)] = true;
        }

        $merged = $static;

        foreach ($catalog as $entry) {
            $key = $this->entryKey($entry);
            if (isset($keys[$key])) {
                continue;
            }
            $keys[$key] = true;
            $merged[] = $entry;
        }

        return $merged;
    }

    private function entryKey(array $entry): string
    {
        return ($entry['dashboard'] ?? '*').'|'.($entry['page'] ?? '*').'|'.($entry['title'] ?? '');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        // إزالة التشكيل
        $value = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{0652}\x{0670}]/u', '', $value) ?? $value;
        // توحيد الألف والياء والتاء المربوطة والتطويل
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = str_replace('ـ', '', $value);
        // إزالة الرموز غير الحروف/الأرقام
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
