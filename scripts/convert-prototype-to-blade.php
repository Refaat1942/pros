<?php

/**
 * تحويل HTML prototype → Blade partials (تشغيل لمرة واحدة)
 */
$prototypeDir = 'D:/freelance/factories and mediciens';
$viewsDir = 'D:/Heard/prosthetics/resources/views';

$map = [
    'reception-dashboard.html' => 'reception/partials/content.blade.php',
    'doctor-dashboard.html' => 'doctor/partials/content.blade.php',
    'spec-dashboard.html' => 'spec/partials/content.blade.php',
    'adjustments-dashboard.html' => 'adjustments/partials/content.blade.php',
    'operations-dashboard.html' => 'operations/partials/content.blade.php',
    'technical-dashboard.html' => 'technical/partials/content.blade.php',
    'admin-dashboard.html' => 'admin/partials/content.blade.php',
];

function convertHtmlToBlade(string $html): array
{
    // استخراج title
    preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch);
    $title = $titleMatch[1] ?? '';

    // استخراج body attributes
    preg_match('/<body([^>]*)>/i', $html, $bodyMatch);
    $bodyAttr = trim($bodyMatch[1] ?? '');

    // استخراج styles من head
    preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $styleMatches);
    $styles = [];
    foreach ($styleMatches[0] as $link) {
        if (str_contains($link, 'fonts.googleapis.com')) {
            continue;
        }
        if (preg_match('/href=["\']([^"\']+)["\']/', $link, $m)) {
            $href = $m[1];
            if (str_starts_with($href, 'assets/')) {
                $styles[] = $href;
            }
        }
    }

    // استخراج scripts
    preg_match_all('/<script[^>]+src=["\']([^"\']+)["\'][^>]*><\/script>/i', $html, $scriptMatches);
    $scripts = $scriptMatches[1] ?? [];

    // محتوى body بدون scripts
    preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $bodyContent);
    $content = $bodyContent[1] ?? $html;

    // إزالة script tags من المحتوى
    $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
    $content = trim($content);

    // تحويل المسارات
    $content = preg_replace_callback(
        '/(href|src)=["\']assets\/([^"\']+)["\']/',
        fn ($m) => $m[1].'="{{ asset(\'assets/'.$m[2].'\') }}"',
        $content
    );
    $content = str_replace('href="index.php"', 'href="{{ route(\'home\') }}"', $content);
    $content = str_replace("href='index.php'", "href=\"{{ route('home') }}\"", $content);

    return compact('title', 'bodyAttr', 'styles', 'scripts', 'content');
}

$meta = [];

foreach ($map as $htmlFile => $bladePath) {
    $html = file_get_contents("$prototypeDir/$htmlFile");
    $converted = convertHtmlToBlade($html);
    $role = explode('/', $bladePath)[0];
    $meta[$role] = $converted;

    $dir = dirname("$viewsDir/$bladePath");
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents("$viewsDir/$bladePath", $converted['content']);
    echo "OK: $bladePath\n";
}

// index.html
$indexHtml = file_get_contents("$prototypeDir/index.html");
$indexBody = convertHtmlToBlade($indexHtml);
$indexContent = $indexBody['content'];
$indexContent = preg_replace(
    '/href="([a-z-]+)-dashboard\.html"/',
    'href="{{ route(\'$1.dashboard\') }}"',
    $indexContent
);
// Fix technical route name (inventory card links to technical-dashboard.html)
$indexContent = str_replace("route('technical-dashboard.dashboard')", "route('technical.dashboard')", $indexContent);
$indexContent = preg_replace(
    '/href="\{\{ route\(\'inventory-dashboard\.dashboard\'\) \}\}"/',
    'href="{{ route(\'technical.dashboard\') }}"',
    $indexContent
);

// Manual fixes for route names
$routeFixes = [
    'reception-dashboard' => 'reception',
    'doctor-dashboard' => 'doctor',
    'spec-dashboard' => 'spec',
    'adjustments-dashboard' => 'adjustments',
    'operations-dashboard' => 'operations',
    'technical-dashboard' => 'technical',
    'admin-dashboard' => 'admin',
];
foreach ($routeFixes as $old => $new) {
    $indexContent = str_replace("route('$old.dashboard')", "route('$new.dashboard')", $indexContent);
}

$indexContent = preg_replace_callback(
    '/(href|src)=["\']assets\/([^"\']+)["\']/',
    fn ($m) => $m[1].'="{{ asset(\'assets/'.$m[2].'\') }}"',
    $indexContent
);

if (! is_dir("$viewsDir")) {
    mkdir($viewsDir, 0777, true);
}
file_put_contents("$viewsDir/index.blade.php", $indexContent);
$meta['home'] = [
    'title' => $indexBody['title'],
    'styles' => ['assets/css/index.css'],
    'scripts' => ['assets/js/pages/index.js'],
    'bodyAttr' => '',
];

file_put_contents("$viewsDir/_dashboard_meta.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "OK: index.blade.php + meta\n";
