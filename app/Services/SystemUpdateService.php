<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * تحديث النظام من المتصفح (مدير النظام) — للسيرفر الأوفلاين (ويندوز/Laragon):
 *   1) الرفع على أجزاء (من غير تعديل حدود PHP).
 *   2) فك آمن (منع ../ والمسارات المطلقة).
 *   3) التحقق من التوقيع (RSA-SHA256 على MANIFEST.json) وبصمة كل ملف — أي تعديل أو ملف زيادة = رفض.
 *   4) الإصدار لازم يكون أحدث من المركّب.
 *   5) تشغيل UPDATE.bat نفسه في الخلفية (نسخة احتياطية ← ملفات ← قاعدة البيانات ← الصلاحيات ← الكاش).
 */
class SystemUpdateService
{
    public function workDir(?string $id = null): string
    {
        $base = storage_path('app/system-update');

        return $id === null ? $base : $base.DIRECTORY_SEPARATOR.$id;
    }

    /** @return array{version: ?string, commit: ?string} */
    public function currentVersion(): array
    {
        $info = $this->parseVersionFile(base_path('VERSION.txt'));

        return ['version' => $info['version'] ?? null, 'commit' => $info['commit'] ?? null];
    }

    public function startUpload(int $userId, int $size): string
    {
        if ($size <= 0 || $size > (int) config('system_update.max_bytes')) {
            throw new RuntimeException('حجم ملف التحديث غير صالح.');
        }
        $id = Str::lower(Str::random(24));
        File::ensureDirectoryExists($this->workDir($id));
        File::put($this->workDir($id).'/meta.json', json_encode(['user_id' => $userId, 'size' => $size, 'received' => 0, 'next' => 0]));
        File::put($this->workDir($id).'/package.zip', '');

        return $id;
    }

    public function appendChunk(string $id, int $userId, int $index, string $bytes): int
    {
        $meta = $this->meta($id, $userId);
        if ($index !== (int) $meta['next']) {
            throw new RuntimeException('ترتيب أجزاء الملف غير صحيح — أعد الرفع.');
        }
        if ($meta['received'] + strlen($bytes) > $meta['size']) {
            throw new RuntimeException('الملف المرفوع أكبر من الحجم المعلن.');
        }
        file_put_contents($this->workDir($id).'/package.zip', $bytes, FILE_APPEND | LOCK_EX);
        $meta['received'] += strlen($bytes);
        $meta['next']++;
        File::put($this->workDir($id).'/meta.json', json_encode($meta));

        return (int) $meta['received'];
    }

    /**
     * يفك ويتحقق — يرجّع جذر الباكدج وبيانات الإصدار. أي فشل = استثناء ولا حاجة تتركّب.
     *
     * @return array{root: string, version: string, commit: string, files: int}
     */
    public function verify(string $id, int $userId): array
    {
        $meta = $this->meta($id, $userId);
        $zipPath = $this->workDir($id).'/package.zip';
        if ((int) $meta['received'] !== (int) $meta['size'] || filesize($zipPath) !== (int) $meta['size']) {
            throw new RuntimeException('الملف لم يكتمل رفعه — أعد الرفع.');
        }

        $dest = $this->workDir($id).'/extracted';
        File::deleteDirectory($dest);
        $this->safeExtract($zipPath, $dest);

        $root = $this->packageRoot($dest);
        $manifestBytes = (string) @file_get_contents($root.'/MANIFEST.json');
        $sig = base64_decode((string) @file_get_contents($root.'/MANIFEST.sig'), true);
        if ($manifestBytes === '' || $sig === false || $sig === '') {
            throw new RuntimeException('ملف التحديث غير موقّع — يُقبل فقط ملف تحديث رسمي موقّع.');
        }

        $publicKey = openssl_pkey_get_public((string) config('system_update.public_key'));
        if ($publicKey === false || openssl_verify($manifestBytes, $sig, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('توقيع ملف التحديث غير صحيح — الملف ليس رسمياً أو تم التعديل عليه.');
        }

        $manifest = json_decode($manifestBytes, true);
        $expected = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $actual = $this->hashTree($root);
        if (count($expected) === 0 || $actual != $expected) {
            throw new RuntimeException('محتوى ملف التحديث لا يطابق التوقيع (ملف ناقص أو زائد أو معدَّل).');
        }
        if (! is_file($root.'/UPDATE.bat') || ! is_file($root.'/files/artisan')) {
            throw new RuntimeException('ملف التحديث ناقص.');
        }

        $version = (string) ($manifest['version'] ?? '');
        $current = (string) ($this->currentVersion()['version'] ?? '');
        if ($version === '' || ($current !== '' && strcmp($version, $current) <= 0)) {
            throw new RuntimeException("هذا الإصدار ({$version}) ليس أحدث من المركّب حالياً ({$current}).");
        }

        return ['root' => $root, 'version' => $version, 'commit' => (string) ($manifest['commit'] ?? ''), 'files' => count($expected)];
    }

    /**
     * يشغّل UPDATE.bat في الخلفية — يرجّع رمز متابعة التقدّم.
     */
    public function launch(string $root, string $version): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('التحديث من المتصفح متاح على السيرفر الأوفلاين (ويندوز) فقط — الأونلاين يتحدّث بـ deploy.sh.');
        }

        $token = Str::lower(Str::random(32));
        $log = storage_path('logs/system-update-'.date('Ymd-His').'.log');
        $app = base_path();
        $laragon = $this->guessLaragonRoot($app);
        $launcher = $root.DIRECTORY_SEPARATOR.'launch-update.cmd';
        File::put($launcher, implode("\r\n", [
            '@echo off',
            'set UPDATE_NONINTERACTIVE=1',
            $laragon ? 'set "LARAGON_ROOT='.$laragon.'"' : 'rem no laragon root',
            'call "'.$root.DIRECTORY_SEPARATOR.'UPDATE.bat" "'.$app.'" > "'.$log.'" 2>&1',
            'echo UPDATE EXIT CODE %errorlevel% >> "'.$log.'"',
            '',
        ]));
        File::put($this->workDir().'/current.json', json_encode([
            'token' => $token, 'log' => $log, 'version' => $version, 'started_at' => now()->toIso8601String(),
        ]));

        $handle = popen('start "" /B cmd /c "'.$launcher.'"', 'r');
        if ($handle === false) {
            throw new RuntimeException('تعذّر تشغيل عملية التحديث على السيرفر.');
        }
        pclose($handle);

        return $token;
    }

    /** يمنع ../ والمسارات المطلقة قبل الفك (Zip Slip). */
    private function safeExtract(string $zipPath, string $dest): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ملف التحديث ليس ZIP صالحاً.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || str_contains($name, ':')) {
                $zip->close();
                throw new RuntimeException('ملف التحديث يحتوي على مسارات غير آمنة.');
            }
        }
        File::ensureDirectoryExists($dest);
        if (! $zip->extractTo($dest)) {
            $zip->close();
            throw new RuntimeException('تعذّر فك ملف التحديث.');
        }
        $zip->close();
    }

    private function packageRoot(string $dest): string
    {
        if (is_file($dest.'/MANIFEST.json')) {
            return $dest;
        }
        $dirs = array_values(array_filter(glob($dest.'/*') ?: [], 'is_dir'));
        if (count($dirs) === 1 && is_file($dirs[0].'/MANIFEST.json')) {
            return $dirs[0];
        }
        throw new RuntimeException('ملف التحديث غير موقّع — يُقبل فقط ملف تحديث رسمي موقّع.');
    }

    /** @return array<string, string> */
    private function hashTree(string $root): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if (in_array($rel, ['MANIFEST.json', 'MANIFEST.sig', 'launch-update.cmd'], true)) {
                continue;
            }
            $out[$rel] = hash_file('sha256', $f->getPathname());
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    /** @return array{user_id: int, size: int, received: int, next: int} */
    private function meta(string $id, int $userId): array
    {
        if (! preg_match('/^[a-z0-9]{24}$/', $id) || ! is_file($this->workDir($id).'/meta.json')) {
            throw new RuntimeException('جلسة الرفع غير موجودة — أعد الرفع.');
        }
        $meta = json_decode((string) file_get_contents($this->workDir($id).'/meta.json'), true);
        if ((int) ($meta['user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('جلسة الرفع تخص مستخدماً آخر.');
        }

        return $meta;
    }

    /** @return array<string, string> */
    private function parseVersionFile(string $path): array
    {
        $out = [];
        foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $out[trim($k)] = trim($v);
            }
        }

        return $out;
    }

    private function guessLaragonRoot(string $app): ?string
    {
        $norm = str_replace('/', '\\', $app);
        $pos = stripos($norm, '\\www\\');

        return $pos !== false && is_dir(substr($norm, 0, $pos).'\\bin\\php') ? substr($norm, 0, $pos) : null;
    }
}
