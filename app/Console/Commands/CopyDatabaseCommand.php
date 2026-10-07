<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نقل كل البيانات من قاعدة قديمة (مثلاً PostgreSQL) إلى القاعدة الحالية في .env (مثلاً MySQL).
 *
 * القاعدة الجديدة تُنشأ أولاً بـ php artisan migrate، ثم يُنسخ كل جدول كما هو (نفس الأرقام والعلاقات)،
 * وفي النهاية يُقارن عدد الصفوف جدولاً بجدول. القاعدة القديمة لا يُكتب فيها شيء.
 */
class CopyDatabaseCommand extends Command
{
    protected $signature = 'prosthetics:copy-database
                            {--driver=pgsql : نوع القاعدة القديمة (pgsql أو mysql)}
                            {--host=127.0.0.1}
                            {--port= : الافتراضي 5432 لـ pgsql و 3306 لـ mysql}
                            {--database= : اسم القاعدة القديمة}
                            {--username= : مستخدم القاعدة القديمة}
                            {--password= : كلمة السر (إن تُركت تُطلب بدون إظهار)}
                            {--force : تنفيذ بدون تأكيد}';

    protected $description = 'Copy every table from an old database (e.g. PostgreSQL) into the current DB_CONNECTION (e.g. MySQL), then verify row counts';

    private const CHUNK = 500;

    public function handle(): int
    {
        $driver = (string) $this->option('driver');
        if (! in_array($driver, ['pgsql', 'mysql'], true)) {
            $this->error('--driver يجب أن يكون pgsql أو mysql.');

            return self::FAILURE;
        }

        $database = (string) $this->option('database');
        $username = (string) $this->option('username');
        if ($database === '' || $username === '') {
            $this->error('حدّد --database و --username للقاعدة القديمة.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ($password === null) {
            $password = (string) $this->secret('كلمة سر القاعدة القديمة');
        }

        config(['database.connections.copy_source' => array_merge(
            config('database.connections.'.$driver),
            [
                'host' => (string) $this->option('host'),
                'port' => (string) ($this->option('port') ?: ($driver === 'pgsql' ? '5432' : '3306')),
                'database' => $database,
                'username' => $username,
                'password' => (string) $password,
                'url' => null,
            ],
        )]);

        $source = DB::connection('copy_source');
        $target = DB::connection();

        if ($source->getDriverName() === $target->getDriverName()
            && $source->getConfig('host') === $target->getConfig('host')
            && $source->getDatabaseName() === $target->getDatabaseName()) {
            $this->error('القاعدة القديمة هي نفسها القاعدة الحالية — لا يوجد ما يُنقل.');

            return self::FAILURE;
        }

        try {
            $source->getPdo();
        } catch (\Throwable $e) {
            $this->error('تعذّر الاتصال بالقاعدة القديمة: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! Schema::hasTable('migrations')) {
            $this->error('القاعدة الحالية فارغة — شغّل php artisan migrate --force أولاً.');

            return self::FAILURE;
        }

        // نفس نسخة البرنامج على القاعدتين — وإلا تختلف الأعمدة أو تضيع بيانات حوّلتها migration لاحقة.
        $sourceMigrations = $source->table('migrations')->pluck('migration')->all();
        $targetMigrations = $target->table('migrations')->pluck('migration')->all();
        $missingInSource = array_diff($targetMigrations, $sourceMigrations);
        $missingInTarget = array_diff($sourceMigrations, $targetMigrations);
        if ($missingInSource !== [] || $missingInTarget !== []) {
            $this->error('القاعدتان ليستا على نفس نسخة البرنامج.');
            if ($missingInSource !== []) {
                $this->line('ناقص في القديمة ('.count($missingInSource).'): شغّل php artisan migrate --force عليها أولاً وهي ما زالت في .env.');
            }
            if ($missingInTarget !== []) {
                $this->line('ناقص في الجديدة ('.count($missingInTarget).'): شغّل php artisan migrate --force على الجديدة.');
            }

            return self::FAILURE;
        }

        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $table) => $table === 'migrations')
            ->sort()
            ->values();

        if (! $this->option('force') && ! $this->confirm(
            "سيُمسح محتوى القاعدة الحالية ({$target->getDriverName()}: {$target->getDatabaseName()}) "
            ."ويُستبدل بنسخة من القديمة ({$driver}: {$database}) — {$tables->count()} جدول. متابعة؟",
            false,
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = false;

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (! $source->getSchemaBuilder()->hasTable($table)) {
                    $this->warn("  {$table}: غير موجود في القديمة — تُرك فارغاً.");
                    $target->table($table)->delete();
                    $rows[] = [$table, '—', $target->table($table)->count(), '—'];

                    continue;
                }

                $copied = $this->copyTable($source, $target, $table);
                $sourceCount = $source->table($table)->count();
                $targetCount = $target->table($table)->count();
                $ok = $sourceCount === $targetCount && $copied === $sourceCount;
                $failed = $failed || ! $ok;
                $rows[] = [$table, $sourceCount, $targetCount, $ok ? '✓' : '✗'];
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($target->getDriverName() === 'pgsql') {
            $this->resetPostgresSequences($target, $tables->all());
        }

        $this->table(['الجدول', 'القديمة', 'الجديدة', ''], $rows);

        if ($failed) {
            $this->error('بعض الجداول لم تتطابق — لا تشغّل النظام على القاعدة الجديدة قبل مراجعة الجداول المعلّمة ✗.');

            return self::FAILURE;
        }

        $this->info('تم النقل وكل الجداول متطابقة. بعدها: php artisan prosthetics:sync-permissions ثم php artisan optimize:clear');

        return self::SUCCESS;
    }

    private function copyTable(Connection $source, Connection $target, string $table): int
    {
        $columns = array_values(array_intersect(
            $target->getSchemaBuilder()->getColumnListing($table),
            $source->getSchemaBuilder()->getColumnListing($table),
        ));

        $target->table($table)->delete();

        $query = $source->table($table)->select($columns);
        $copied = 0;

        $insert = function ($chunk) use ($target, $table, &$copied) {
            $batch = $chunk->map(fn ($row) => array_map(
                // PostgreSQL يعيد true/false — MySQL في الوضع الصارم يرفض '' كرقم.
                fn ($value) => is_bool($value) ? (int) $value : $value,
                (array) $row,
            ))->all();

            if ($batch !== []) {
                $target->table($table)->insert($batch);
                $copied += count($batch);
            }
        };

        if (in_array('id', $columns, true)) {
            $query->chunkById(self::CHUNK, $insert, 'id');
        } else {
            $query->orderBy($columns[0])->chunk(self::CHUNK, $insert);
        }

        return $copied;
    }

    /** بعد إدخال أرقام id صريحة في PostgreSQL يجب تحريك العدّاد، وإلا يتعارض أول إدخال جديد. */
    private function resetPostgresSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasColumn($table, 'id')) {
                continue;
            }

            $sequence = $target->selectOne('select pg_get_serial_sequence(?, ?) as seq', [$table, 'id'])->seq ?? null;
            if ($sequence) {
                $target->statement("select setval('{$sequence}', coalesce((select max(id) from \"{$table}\"), 0) + 1, false)");
            }
        }
    }
}
