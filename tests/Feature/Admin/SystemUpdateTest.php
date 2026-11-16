<?php

namespace Tests\Feature\Admin;

use App\Services\SystemUpdateService;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;
use ZipArchive;

/** «تحديث النظام»: باكدج موقّع فقط، بدون تعديل/زيادة/مسارات خطرة/إصدار أقدم، ومدير النظام بالباسورد. */
class SystemUpdateTest extends TestCase
{
    use ProstheticTestHelper;

    private string $tmp;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/su-test-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyFile = $this->tmp.'/key.pem';
        File::put($this->keyFile, $pem);
        config(['system_update.public_key' => openssl_pkey_get_details($key)['key']]);
        File::deleteDirectory(storage_path('app/system-update'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        File::deleteDirectory(storage_path('app/system-update'));
        @unlink(base_path('VERSION.txt.test-backup'));
        parent::tearDown();
    }

    /** @param  callable(string): void|null  $tamper */
    private function package(string $version = '20991231-2359', bool $sign = true, ?callable $tamper = null, array $extraZipEntries = []): string
    {
        $root = $this->tmp.'/build-'.uniqid().'/prosthetics-offline-'.$version;
        File::ensureDirectoryExists($root.'/files/app');
        File::put($root.'/UPDATE.bat', "@echo off\r\necho DONE - update installed\r\n");
        File::put($root.'/files/artisan', '<?php // artisan');
        File::put($root.'/files/app/Example.php', '<?php // v'.$version);
        File::put($root.'/VERSION.txt', "version={$version}\ncommit=abc\nbuilt_utc=x\n");
        if ($sign) {
            exec('php '.escapeshellarg(base_path('scripts/offline-package/sign.php')).' '.escapeshellarg($root).' '.escapeshellarg($this->keyFile), $o, $code);
            $this->assertSame(0, $code, implode("\n", $o));
        }
        if ($tamper) {
            $tamper($root);
        }
        $zipPath = $this->tmp.'/pkg-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $base = dirname($root);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($base) + 1));
        }
        foreach ($extraZipEntries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $zipPath;
    }

    private function verify(string $zipPath): array
    {
        $svc = app(SystemUpdateService::class);
        $bytes = file_get_contents($zipPath);
        $id = $svc->startUpload(1, strlen($bytes));
        foreach (str_split($bytes, 1024 * 1024) as $i => $chunk) {
            $svc->appendChunk($id, 1, $i, $chunk);
        }

        return $svc->verify($id, 1);
    }

    private function assertRejected(string $zipPath, string $needle): void
    {
        try {
            $this->verify($zipPath);
            $this->fail('كان لازم يترفض');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    public function test_signed_package_is_accepted(): void
    {
        $pkg = $this->verify($this->package());
        $this->assertSame('20991231-2359', $pkg['version']);
        $this->assertFileExists($pkg['root'].'/UPDATE.bat');
    }

    public function test_tampered_extra_unsigned_and_unsafe_packages_are_rejected(): void
    {
        $this->assertRejected($this->package(tamper: fn ($r) => File::put($r.'/files/app/Example.php', '<?php evil();')), 'لا يطابق');
        $this->assertRejected($this->package(tamper: fn ($r) => File::put($r.'/files/app/Backdoor.php', '<?php evil();')), 'لا يطابق');
        $this->assertRejected($this->package(tamper: fn ($r) => File::put($r.'/MANIFEST.json', str_replace('abc', 'zzz', File::get($r.'/MANIFEST.json')))), 'توقيع');
        $this->assertRejected($this->package(sign: false), 'غير موقّع');
        $this->assertRejected($this->package(extraZipEntries: ['../../evil.php' => 'x']), 'غير آمنة');

        // توقيع بمفتاح تاني (مش مفتاح النظام) → مرفوض.
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $pem);
        File::put($this->keyFile, $pem);
        $this->assertRejected($this->package(), 'توقيع');
    }

    public function test_older_or_same_version_is_rejected(): void
    {
        $versionFile = base_path('VERSION.txt');
        $hadFile = is_file($versionFile);
        if ($hadFile) {
            rename($versionFile, $versionFile.'.test-backup');
        }
        try {
            File::put($versionFile, "version=20991231-2359\ncommit=x\n");
            $this->assertRejected($this->package('20991231-2359'), 'ليس أحدث');
            $this->assertRejected($this->package('20200101-0000'), 'ليس أحدث');
        } finally {
            @unlink($versionFile);
            if ($hadFile) {
                rename($versionFile.'.test-backup', $versionFile);
            }
        }
    }

    public function test_only_super_admin_with_password_and_full_http_flow(): void
    {
        $zip = file_get_contents($this->package());

        // مسؤول محدود الصلاحيات حتى لو اتمنح الصفحة → ممنوع.
        $role = $this->makeRole(\App\Models\Role::SLUG_ADMIN);
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();
        $role->permissions()->sync(\App\Models\Permission::where('slug', 'admin.system-update.view')->pluck('id'));
        $limited = \App\Models\User::create(['name' => 'مسؤول', 'username' => 'lim_su', 'password' => 'password', 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($limited)->postJson('/admin/system-update/start', ['password' => 'password', 'size' => strlen($zip)])->assertForbidden();

        $boss = $this->userWithRole('super_admin');
        $this->actingAs($boss)->postJson('/admin/system-update/start', ['password' => 'wrong', 'size' => strlen($zip)])->assertStatus(422);

        $start = $this->actingAs($boss)->postJson('/admin/system-update/start', ['password' => 'password', 'size' => strlen($zip)])->assertOk();
        $id = $start->json('id');
        foreach (str_split($zip, (int) $start->json('chunk_bytes')) as $i => $chunk) {
            $this->actingAs($boss)->call('POST', "/admin/system-update/{$id}/chunk?index={$i}", [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json',
            ], $chunk)->assertOk();
        }

        // التوقيع سليم — على Linux التركيب نفسه مرفوض برسالة واضحة (ويندوز فقط).
        $finish = $this->actingAs($boss)->postJson("/admin/system-update/{$id}/finish");
        if (PHP_OS_FAMILY !== 'Windows') {
            $finish->assertStatus(422)->assertJsonFragment(['message' => 'التحديث من المتصفح متاح على السيرفر الأوفلاين (ويندوز) فقط — الأونلاين يتحدّث بـ deploy.sh.']);
        }
        $this->assertTrue(\App\Models\AuditLog::where('description', 'like', 'تحديث النظام من المتصفح%')->exists(), 'اتسجّل في سجل الرقابة');
    }

    public function test_page_renders_for_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get('/admin/system-update')
            ->assertOk()->assertSee('رفع وتحديث', false);
    }
}
