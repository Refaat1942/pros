<?php

/**
 * Split monolithic dashboard content.blade.php into per-page partials.
 */
$base = dirname(__DIR__).'/resources/views';

$dashboards = [
    'admin' => [
        'prefix' => 'section-',
        'prepend' => [],
        'pages' => ['overview', 'bi', 'catalog', 'pricing', 'cases', 'employees', 'companies', 'debts', 'audit', 'reports', 'suppliers'],
    ],
    'reception' => [
        'prefix' => 'tab-',
        'prepend' => [
            'appointments' => ['id:appointmentsCalendarWrap', 'id:addPatientSection', 'id:analytics-reception-main'],
        ],
        'pages' => ['appointments', 'ocr', 'quote', 'delivery', 'selfservice', 'patients'],
    ],
    'doctor' => [
        'prefix' => 'section-',
        'prepend' => [
            'queue' => ['class:stats-row'],
        ],
        'pages' => ['queue', 'records', 'transfer'],
    ],
    'spec' => [
        'prefix' => 'section-',
        'prepend' => [
            'orders' => ['id:analytics-orders'],
        ],
        'pages' => ['orders', 'spec', 'pricing'],
    ],
    'technical' => [
        'prefix' => 'section-',
        'prepend' => [],
        'pages' => ['inventory', 'bom', 'returns'],
    ],
    'adjustments' => [
        'prefix' => 'section-',
        'prepend' => [],
        'pages' => ['adjustments'],
    ],
    'operations' => [
        'prefix' => 'section-',
        'prepend' => [],
        'pages' => ['operations'],
    ],
];

function extractDivBlockFromLines(array $lines, int $startLine): string
{
    $depth = 0;
    $out = [];
    for ($i = $startLine; $i < count($lines); $i++) {
        $line = $lines[$i];
        $out[] = $line;
        $opens = preg_match_all('/<div[\s>]/', $line);
        $closes = substr_count($line, '</div>');
        if ($i === $startLine) {
            $depth = $opens - $closes;
        } else {
            $depth += $opens - $closes;
        }
        if ($i > $startLine && $depth <= 0) {
            break;
        }
    }

    return implode("\n", $out);
}

function findLineWithNeedle(array $lines, string $needle): ?int
{
    foreach ($lines as $i => $line) {
        if (str_contains($line, $needle)) {
            return $i;
        }
    }

    return null;
}

function cleanBlock(string $block): string
{
    $block = preg_replace('/\bsection-view active\b/', 'section-view', $block);
    $block = preg_replace('/\btab-content active\b/', 'tab-content', $block);

    return trim($block)."\n";
}

foreach ($dashboards as $role => $cfg) {
    $contentPath = "{$base}/{$role}/partials/content.blade.php";
    if (! is_file($contentPath)) {
        continue;
    }
    $lines = file($contentPath, FILE_IGNORE_NEW_LINES);
    $html = implode("\n", $lines);
    $mainEnd = findLineWithNeedle($lines, '</main>');
    if ($mainEnd === null) {
        echo "Skip {$role}: no main end\n";

        continue;
    }

    $pagesDir = "{$base}/{$role}/pages";
    if (! is_dir($pagesDir)) {
        mkdir($pagesDir, 0777, true);
    }

    foreach ($cfg['pages'] as $page) {
        $blockId = $cfg['prefix'].$page;
        $start = findLineWithNeedle($lines, 'id="'.$blockId.'"');
        if ($start === null) {
            echo "WARN {$role}/{$page}: not found\n";

            continue;
        }
        while ($start > 0 && ! str_contains($lines[$start], '<div')) {
            $start--;
        }
        $body = cleanBlock(extractDivBlockFromLines($lines, $start));

        if (! empty($cfg['prepend'][$page])) {
            $prefix = '';
            foreach ($cfg['prepend'][$page] as $marker) {
                [$type, $value] = explode(':', $marker, 2);
                $needle = $type === 'id' ? 'id="'.$value.'"' : 'class="'.$value.'"';
                $pStart = findLineWithNeedle(array_slice($lines, 0, $mainEnd), $needle);
                if ($pStart === null) {
                    continue;
                }
                while ($pStart > 0 && ! preg_match('/<(?:div|section)[\s>]/', $lines[$pStart])) {
                    $pStart--;
                }
                $tag = str_contains($lines[$pStart], '<section') ? 'section' : 'div';
                if ($tag === 'section') {
                    $chunk = [];
                    for ($j = $pStart; $j < $mainEnd; $j++) {
                        $chunk[] = $lines[$j];
                        if (str_contains($lines[$j], '</section>')) {
                            break;
                        }
                    }
                    $prefix .= cleanBlock(implode("\n", $chunk));
                } else {
                    $prefix .= cleanBlock(extractDivBlockFromLines($lines, $pStart));
                }
            }
            $body = $prefix."\n".$body;
        }

        file_put_contents("{$pagesDir}/{$page}.blade.php", $body);
        echo "OK {$role}/pages/{$page}.blade.php\n";
    }

    $modals = array_slice($lines, $mainEnd);
    file_put_contents("{$base}/{$role}/partials/modals.blade.php", implode("\n", $modals));
    echo "OK {$role}/partials/modals.blade.php\n";
}

echo "Done.\n";
