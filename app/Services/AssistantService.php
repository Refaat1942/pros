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

        return array_map([$this, 'present'], array_slice($merged, 0, $limit));
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

        foreach ($this->accessibleEntries($user) as $index => $entry) {
            $score = $this->scoreEntry($entry, $normalizedQuery, $tokens);

            if ($score <= 0) {
                continue;
            }

            if ($this->matchesContext($entry, $dashboard, $page)) {
                $score += 3;
            }

            $scored[] = ['score' => $score, 'order' => $index, 'entry' => $entry];
        }

        usort($scored, function (array $a, array $b) {
            return $b['score'] <=> $a['score'] ?: $a['order'] <=> $b['order'];
        });

        $results = array_map(
            fn (array $row) => $this->present($row['entry']),
            array_slice($scored, 0, $limit)
        );

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
    private function present(array $entry): array
    {
        return [
            'title' => $entry['title'] ?? '',
            'answer' => $entry['answer'] ?? '',
            'steps' => array_values($entry['steps'] ?? []),
            'dashboard' => $entry['dashboard'] ?? '*',
            'page' => $entry['page'] ?? '*',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function all(): array
    {
        if ($this->knowledge === null) {
            $path = resource_path('assistant/knowledge.php');
            $data = is_file($path) ? require $path : [];
            $this->knowledge = is_array($data) ? $data : [];
        }

        return $this->knowledge;
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
