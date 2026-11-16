<?php
/**
 * توقيع باكدج الأوفلاين: MANIFEST.json (بصمة SHA-256 لكل ملف + الإصدار) + MANIFEST.sig (RSA-SHA256).
 *   php sign.php <package-dir> <private-key.pem>
 * المفتاح السري عمره ما يتحط في المستودع ولا ينزل للعميل.
 */
if ($argc < 3) {
    fwrite(STDERR, "usage: php sign.php <package-dir> <private-key.pem>\n");
    exit(2);
}
[$_, $dir, $keyFile] = $argv;
$dir = rtrim(realpath($dir) ?: $dir, '/');
$key = openssl_pkey_get_private((string) @file_get_contents($keyFile));
if ($key === false) {
    fwrite(STDERR, "cannot read private key\n");
    exit(1);
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! $f->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
    if ($rel === 'MANIFEST.json' || $rel === 'MANIFEST.sig') {
        continue;
    }
    $files[$rel] = hash_file('sha256', $f->getPathname());
}
ksort($files, SORT_STRING);

$version = [];
foreach (file($dir.'/VERSION.txt', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (str_contains($line, '=')) {
        [$k, $v] = explode('=', $line, 2);
        $version[trim($k)] = trim($v);
    }
}

$manifest = json_encode([
    'format' => 1,
    'version' => $version['version'] ?? '',
    'commit' => $version['commit'] ?? '',
    'built_utc' => $version['built_utc'] ?? '',
    'files' => $files,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

if (! openssl_sign($manifest, $sig, $key, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "signing failed\n");
    exit(1);
}
file_put_contents($dir.'/MANIFEST.json', $manifest);
file_put_contents($dir.'/MANIFEST.sig', base64_encode($sig));
echo 'signed '.count($files).' files, version '.($version['version'] ?? '?')."\n";
