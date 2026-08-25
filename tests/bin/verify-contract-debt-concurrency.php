<?php

/**
 * P1-01 real-database concurrency verification (PostgreSQL / MySQL).
 *
 * Spawns two PHP worker processes that each bootstrap Laravel and call
 * ContractDebtService::recordPayment() against the same debt row so the
 * second connection blocks on SELECT … FOR UPDATE until the first commits.
 *
 * Usage (PostgreSQL example):
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
 *   DB_DATABASE=prosthetics DB_USERNAME=prosthetics_user DB_PASSWORD=test \
 *   php tests/bin/verify-contract-debt-concurrency.php
 */

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Services\ContractDebtService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$baseDir = dirname(__DIR__, 2);
chdir($baseDir);

require $baseDir.'/vendor/autoload.php';

// ── Worker mode (child process) ──────────────────────────────────────────────

if (($argv[1] ?? '') === '--worker') {
    $mode = $argv[2] ?? '';
    $companyId = (int) ($argv[3] ?? 0);
    $resultPath = $argv[4] ?? '';

    if ($companyId <= 0 || $resultPath === '' || ! in_array($mode, ['race', 'hold-lock'], true)) {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(2);
    }

    $app = bootstrapApp();
    $startedAt = microtime(true);

    $company = ContractCompany::query()->findOrFail($companyId);
    $service = app(ContractDebtService::class);

    $result = [
        'mode' => $mode,
        'pid' => getmypid(),
        'started_at' => $startedAt,
        'status' => 'unknown',
        'message' => null,
        'elapsed_ms' => null,
    ];

    try {
        if ($mode === 'hold-lock') {
            DB::beginTransaction();
            ContractCompanyDebt::query()
                ->where('contract_company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();
            // Hold row lock so the peer worker must wait before its own lockForUpdate().
            usleep(2_000_000);
            $service->recordPayment($company, 1000);
            DB::commit();
        } else {
            $service->recordPayment($company, 1000);
        }

        $result['status'] = 'success';
    } catch (\InvalidArgumentException $e) {
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

// ── Orchestrator ─────────────────────────────────────────────────────────────

$driver = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');

if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
    fwrite(STDERR, "DB_CONNECTION must be pgsql or mysql/mariadb (got: {$driver}).\n");
    exit(1);
}

$app = bootstrapApp();
$kernel = $app->make(Kernel::class);
$kernel->call('migrate:fresh', ['--force' => true]);

$raceReport = runScenario($driver, 'race', holdLock: false);
$lockReport = runScenario($driver, 'hold-lock', holdLock: true);

echo json_encode([
    'race' => $raceReport,
    'lock_wait_scenario' => $lockReport,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

exit($raceReport['passed'] && $lockReport['passed'] ? 0 : 1);

function bootstrapApp(): Application
{
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

/**
 * @return array<string, mixed>
 */
function runScenario(string $driver, string $label, bool $holdLock): array
{
    $company = ContractCompany::query()->create([
        'company_code' => 'CO-PG-'.strtoupper(substr($label, 0, 4)),
        'name' => "تحقق تزامن {$label}",
        'is_military' => false,
        'is_contracted' => true,
    ]);

    $debt = ContractCompanyDebt::query()->create([
        'contract_company_id' => $company->id,
        'due' => 1000,
        'collected' => 0,
        'status' => 'pending',
    ]);

    $tmpDir = sys_get_temp_dir().'/contract-debt-concurrency-'.getmypid();
    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0700, true);
    }

    $workerScript = dirname(__DIR__).'/bin/verify-contract-debt-concurrency.php';
    $phpBinary = PHP_BINARY;
    $env = array_merge($_ENV, $_SERVER);
    $env['APP_ENV'] = 'testing';

    $modes = $holdLock ? ['hold-lock', 'race'] : ['race', 'race'];
    $resultPaths = [];
    $processes = [];

    foreach (['A', 'B'] as $index => $suffix) {
        $resultPath = $tmpDir."/result-{$label}-{$suffix}.json";
        $resultPaths[$suffix] = $resultPath;
        @unlink($resultPath);

        $process = new Process(
            [$phpBinary, $workerScript, '--worker', $modes[$index], (string) $company->id, $resultPath],
            dirname(__DIR__, 2),
            $env
        );
        $process->setTimeout(120);
        $processes[$suffix] = $process;
    }

  // Start both workers as close together as possible.
    $processes['A']->start();
    $processes['B']->start();

    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            fwrite(STDERR, "Worker failed: ".$process->getErrorOutput()."\n");
        }
    }

    $workerResults = [];
    foreach ($resultPaths as $suffix => $path) {
        $workerResults[$suffix] = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    $debt->refresh();

    $auditPaymentCount = AuditLog::query()
        ->where('action', 'payment')
        ->where('tag', 'financial')
        ->where('description', 'like', '%تحصيل%')
        ->where('description', 'like', '%'.$company->name.'%')
        ->count();

    $historyCount = DebtCollectionEntry::query()
        ->where('payable_id', $debt->id)
        ->count();

    $successes = array_values(array_filter($workerResults, fn (array $r) => $r['status'] === 'success'));
    $failures = array_values(array_filter($workerResults, fn (array $r) => $r['status'] === 'rejected'));

    $passed = count($successes) === 1
        && count($failures) === 1
        && (float) $debt->collected === 1000.0
        && (float) $debt->collected !== 2000.0
        && $historyCount === 1
        && $auditPaymentCount === 1;

    if ($holdLock) {
        $waiter = $workerResults['B'];
        $passed = $passed && ($waiter['elapsed_ms'] ?? 0) >= 1500;
    }

    return [
        'label' => $label,
        'driver' => $driver,
        'debt_id' => $debt->id,
        'company_id' => $company->id,
        'workers' => $workerResults,
        'assertions' => [
            'final_collected' => (float) $debt->collected,
            'final_due' => (float) $debt->due,
            'successful_requests' => count($successes),
            'failed_requests' => count($failures),
            'collection_history_count' => $historyCount,
            'audit_payment_count' => $auditPaymentCount,
        ],
        'lock_wait_verified' => $holdLock ? (($workerResults['B']['elapsed_ms'] ?? 0) >= 1500) : null,
        'passed' => $passed,
    ];
}
