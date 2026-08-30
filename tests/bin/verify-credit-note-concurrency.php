<?php

/**
 * P1-02 real-database concurrency verification for credit note creation.
 *
 * Two worker processes concurrently create 40,000 credit notes on a case with
 * quote_total = 50,000 and no prior credits. Exactly one must succeed.
 *
 * Usage:
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
 *   DB_DATABASE=prosthetics DB_USERNAME=prosthetics_user DB_PASSWORD=test \
 *   php tests/bin/verify-credit-note-concurrency.php
 */

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$baseDir = dirname(__DIR__, 2);
chdir($baseDir);

require $baseDir.'/vendor/autoload.php';

if (($argv[1] ?? '') === '--worker') {
    $caseId = (int) ($argv[2] ?? 0);
    $resultPath = $argv[3] ?? '';

    if ($caseId <= 0 || $resultPath === '') {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(2);
    }

    $app = bootstrapApp();
    $startedAt = microtime(true);

    $case = CaseRecord::query()->findOrFail($caseId);
    $service = app(CreditNoteService::class);

    $result = [
        'pid' => getmypid(),
        'started_at' => $startedAt,
        'status' => 'unknown',
        'message' => null,
        'elapsed_ms' => null,
    ];

    try {
        $note = $service->create($case, CreditNote::TYPE_PARTIAL, 40000, 'تحقق تزامن');
        $result['status'] = 'success';
        $result['credit_note_id'] = $note->id;
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $result['status'] = 'rejected';
        $result['message'] = $e->getMessage();
    } catch (\Throwable $e) {
        $result['status'] = 'error';
        $result['message'] = $e->getMessage();
    }

    $result['elapsed_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
    file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));

    exit(0);
}

$driver = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');

if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
    fwrite(STDERR, "DB_CONNECTION must be pgsql or mysql/mariadb (got: {$driver}).\n");
    exit(1);
}

$app = bootstrapApp();
$kernel = $app->make(Kernel::class);
$kernel->call('migrate:fresh', ['--force' => true]);

$case = setupCase();
$report = runRace($case);
$report['existing_violations'] = findAggregateViolations();

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

exit($report['passed'] ? 0 : 1);

function bootstrapApp(): Application
{
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

function setupCase(): CaseRecord
{
    $company = \App\Models\ContractCompany::query()->create([
        'company_code' => 'CO-CN-PG',
        'name' => 'شركة تحقق إشعار دائن',
        'is_military' => false,
        'is_contracted' => true,
    ]);

    \App\Models\ContractCompanyDebt::query()->create([
        'contract_company_id' => $company->id,
        'due' => 50000,
        'collected' => 0,
        'status' => 'pending',
    ]);

    $patient = \App\Models\Patient::query()->create([
        'patient_code' => 'PG-CN-001',
        'patient_qr' => 'QR-PG-CN-001',
        'tracking_uid' => 'pg-cn-0001',
        'name' => 'مريض تحقق',
        'phone' => '01000000999',
        'national_id' => '29901010100999',
        'patient_type' => \App\Models\Patient::TYPE_CIVILIAN,
        'contract_company_id' => $company->id,
        'company_name' => $company->name,
        'registered_at' => now()->toDateString(),
        'status' => \App\Models\Patient::STATUS_ACTIVE,
    ]);

    return CaseRecord::query()->create([
        'case_no' => 'CASE-PG-CN-001',
        'order_ref' => 'PGCN001',
        'patient_id' => $patient->id,
        'contract_company_id' => $company->id,
        'company_name' => $company->name,
        'patient_type' => \App\Models\Patient::TYPE_CIVILIAN,
        'path' => CaseRecord::PATH_STANDARD,
        'stage_key' => CaseRecord::STAGE_DELIVERED,
        'quote_total' => 50000,
    ]);
}

/**
 * @return array<string, mixed>
 */
function runRace(CaseRecord $case): array
{
    $tmpDir = sys_get_temp_dir().'/credit-note-concurrency-'.getmypid();
    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0700, true);
    }

    $workerScript = dirname(__DIR__).'/bin/verify-credit-note-concurrency.php';
    $phpBinary = PHP_BINARY;
    $env = array_merge($_ENV, $_SERVER);
    $env['APP_ENV'] = 'testing';

    $resultPaths = [];
    $processes = [];

    foreach (['A', 'B'] as $suffix) {
        $resultPath = $tmpDir."/result-{$suffix}.json";
        $resultPaths[$suffix] = $resultPath;
        @unlink($resultPath);

        $process = new Process(
            [$phpBinary, $workerScript, '--worker', (string) $case->id, $resultPath],
            dirname(__DIR__, 2),
            $env
        );
        $process->setTimeout(120);
        $processes[$suffix] = $process;
    }

    $processes['A']->start();
    $processes['B']->start();

    foreach ($processes as $process) {
        $process->wait();
    }

    $workers = [];
    foreach ($resultPaths as $suffix => $path) {
        $workers[$suffix] = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    $activeTotal = round((float) CreditNote::query()
        ->where('case_id', $case->id)
        ->whereIn('status', [CreditNote::STATUS_PENDING, CreditNote::STATUS_APPROVED])
        ->sum('amount'), 2);

    $noteCount = CreditNote::query()->where('case_id', $case->id)->count();

    $auditCreateCount = AuditLog::query()
        ->where('action', 'create')
        ->where('tag', 'financial')
        ->where('description', 'like', '%إشعار دائن%')
        ->count();

    $successes = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'success'));
    $failures = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'rejected'));

    $passed = count($successes) === 1
        && count($failures) === 1
        && $noteCount === 1
        && $activeTotal <= 50000.0
        && $activeTotal !== 80000.0
        && $auditCreateCount === 1;

    return [
        'driver' => getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? ''),
        'case_id' => $case->id,
        'workers' => $workers,
        'assertions' => [
            'final_active_credit_total' => $activeTotal,
            'credit_note_count' => $noteCount,
            'successful_requests' => count($successes),
            'failed_requests' => count($failures),
            'audit_create_count' => $auditCreateCount,
        ],
        'passed' => $passed,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function findAggregateViolations(): array
{
    $violations = [];

    $cases = CaseRecord::query()
        ->where('patient_type', \App\Models\Patient::TYPE_CIVILIAN)
        ->where('quote_total', '>', 0)
        ->get(['id', 'quote_total']);

    foreach ($cases as $case) {
        $ceiling = round((float) $case->quote_total, 2);
        $active = round((float) CreditNote::query()
            ->where('case_id', $case->id)
            ->whereIn('status', [CreditNote::STATUS_PENDING, CreditNote::STATUS_APPROVED])
            ->sum('amount'), 2);

        if ($active > $ceiling) {
            $violations[] = [
                'case_id' => $case->id,
                'ceiling' => $ceiling,
                'active_credit_total' => $active,
            ];
        }
    }

    return $violations;
}
