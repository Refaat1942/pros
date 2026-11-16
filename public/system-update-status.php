<?php
// متابعة تقدّم «تحديث النظام» — مستقل عن Laravel (الملفات بتتبدّل والنظام في وضع الصيانة أثناء التحديث).
// يرد بس لو الرمز يطابق التحديث الجاري (رمز عشوائي بيتعمل مع كل تحديث).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$state = __DIR__.'/../storage/app/system-update/current.json';
$token = (string) ($_GET['t'] ?? '');
$cur = is_file($state) ? json_decode((string) file_get_contents($state), true) : null;

if (! is_array($cur) || $token === '' || ! hash_equals((string) ($cur['token'] ?? ''), $token)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$log = (string) ($cur['log'] ?? '');
$text = is_file($log) ? (string) file_get_contents($log) : '';
$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1252');
$done = str_contains($text, 'DONE - update installed');
$failed = str_contains($text, 'UPDATE STOPPED');

$step = '';
if (preg_match_all('/\[(\d)\/7\]\s*([^\r\n]*)/', $text, $m)) {
    $step = end($m[1]).'/7 '.trim(end($m[2]));
}

echo json_encode([
    'ok' => true,
    'version' => $cur['version'] ?? null,
    'step' => $step,
    'done' => $done,
    'failed' => $failed,
    'log' => mb_substr($text, -4000),
], JSON_UNESCAPED_UNICODE);
