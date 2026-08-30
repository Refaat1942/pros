<?php

/**
 * P1-04 dispense-request concurrency verification (PostgreSQL / MySQL).
 *
 * Spawns independent PHP workers that bootstrap Laravel and exercise
 * StockDispenseRequestService under real row-lock contention.
 *
 * WARNING: orchestrator runs migrate:fresh on the configured database.
 * Use only against an isolated test database — never the operational LAN DB.
 *
 * Usage (PostgreSQL example):
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
 *   DB_DATABASE=prosthetics DB_USERNAME=prosthetics_user DB_PASSWORD=test \
 *   php tests/bin/verify-dispense-concurrency.php
 */

declare(strict_types=1);

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockDispenseRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Services\BomService;
use App\Services\PermissionCatalogService;
use App\Services\StockDispenseRequestService;
use App\Services\WorkshopAssignmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$baseDir = dirname(__DIR__, 2);
chdir($baseDir);

require $baseDir.'/vendor/autoload.php';

// ── Worker mode ──────────────────────────────────────────────────────────────

if (($argv[1] ?? '') === '--worker') {
    $mode = $argv[2] ?? '';
    $contextPath = $argv[3] ?? '';
    $resultPath = $argv[4] ?? '';

    if ($contextPath === '' || $resultPath === '' || $mode === '') {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(2);
    }

    $context = json_decode((string) file_get_contents($contextPath), true, 512, JSON_THROW_ON_ERROR);
    bootstrapApp();

    $startedAt = microtime(true);
    $result = [
        'mode' => $mode,
        'pid' => getmypid(),
        'started_at' => $startedAt,
        'status' => 'unknown',
        'message' => null,
        'elapsed_ms' => null,
    ];

    $service = app(StockDispenseRequestService::class);
    $technical = User::query()->findOrFail((int) $context['technical_user_id']);
    $admin = User::query()->findOrFail((int) $context['admin_user_id']);
    $barcodes = $context['barcodes'];

    try {
        match ($mode) {
            'submit' => $service->submit(
                Bom::query()->findOrFail((int) $context['bom_id']),
                $barcodes,
                $technical,
            ),
            'submit-hold-bom' => (function () use ($context, $technical, $barcodes, $service) {
                DB::beginTransaction();
                Bom::query()->lockForUpdate()->findOrFail((int) $context['bom_id']);
                usleep(2_000_000);
                $service->submit(Bom::query()->findOrFail((int) $context['bom_id']), $barcodes, $technical);
                DB::commit();
            })(),
            'approve' => $service->approve(
                StockDispenseRequest::query()->findOrFail((int) $context['request_id']),
                $admin,
            ),
            'reject' => $service->reject(
                StockDispenseRequest::query()->findOrFail((int) $context['request_id']),
                $admin,
                'رفض تزامن',
            ),
            default => throw new \InvalidArgumentException("Unknown worker mode: {$mode}"),
        };

        $result['status'] = 'success';
    } catch (HttpException $e) {
        $result['status'] = 'rejected';
        $result['message'] = $e->getMessage();
        $result['http_status'] = $e->getStatusCode();
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

$submitReport = runConcurrentSubmit($driver);
$approveRejectReport = runConcurrentApproveReject($driver);
$approveApproveReport = runConcurrentApproveApprove($driver);

echo json_encode([
    'concurrent_submit' => $submitReport,
    'concurrent_approve_reject' => $approveRejectReport,
    'concurrent_approve_approve' => $approveApproveReport,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

$passed = $submitReport['passed']
    && $approveRejectReport['passed']
    && $approveApproveReport['passed'];

exit($passed ? 0 : 1);

function bootstrapApp(): Application
{
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

/**
 * @return array<string, mixed>
 */
function runConcurrentSubmit(string $driver): array
{
    $context = seedDispenseContext();
    $tmpDir = makeTmpDir('submit');
    $workerScript = dirname(__DIR__).'/bin/verify-dispense-concurrency.php';

    $workers = spawnWorkers($workerScript, [
        ['submit', $context, $tmpDir.'/A.json'],
        ['submit-hold-bom', $context, $tmpDir.'/B.json'],
    ]);

    $bom = Bom::query()->findOrFail((int) $context['bom_id']);
    $pendingCount = StockDispenseRequest::query()
        ->where('bom_id', $bom->id)
        ->where('status', StockDispenseRequest::STATUS_PENDING)
        ->count();

    $successes = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'success'));
    $failures = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'rejected'));

    $passed = count($successes) === 1
        && count($failures) === 1
        && $pendingCount === 1
        && $bom->fresh()->stage === Bom::STAGE_RAW
        && StockMovement::query()->where('reference_type', 'bom')->where('reference_id', $bom->id)->count() === 0
        && ($workers['B']['elapsed_ms'] ?? 0) >= 1500;

    return [
        'driver' => $driver,
        'workers' => $workers,
        'assertions' => [
            'successful_submits' => count($successes),
            'failed_submits' => count($failures),
            'final_pending_count' => $pendingCount,
            'bom_stage' => $bom->fresh()->stage,
            'stock_movements' => StockMovement::query()->where('reference_type', 'bom')->where('reference_id', $bom->id)->count(),
        ],
        'passed' => $passed,
    ];
}

/**
 * @return array<string, mixed>
 */
function runConcurrentApproveReject(string $driver): array
{
    $context = seedDispenseContext();
    $service = app(StockDispenseRequestService::class);
    $technical = User::query()->findOrFail((int) $context['technical_user_id']);
    $request = $service->submit(
        Bom::query()->findOrFail((int) $context['bom_id']),
        $context['barcodes'],
        $technical,
    );

    $context['request_id'] = $request->id;
    $tmpDir = makeTmpDir('approve-reject');
    $workerScript = dirname(__DIR__).'/bin/verify-dispense-concurrency.php';

    $workers = spawnWorkers($workerScript, [
        ['approve', $context, $tmpDir.'/A.json'],
        ['reject', $context, $tmpDir.'/B.json'],
    ]);

    $request->refresh();
    $bom = Bom::query()->findOrFail((int) $context['bom_id']);
    $movementCount = StockMovement::query()
        ->where('reference_type', 'bom')
        ->where('reference_id', $bom->id)
        ->where('movement_type', StockMovement::TYPE_ISSUE)
        ->count();

    $successes = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'success'));
    $terminal = in_array($request->status, [
        StockDispenseRequest::STATUS_EXECUTED,
        StockDispenseRequest::STATUS_REJECTED,
    ], true);

    $passed = count($successes) === 1
        && count($workers) - count($successes) === 1
        && $terminal
        && $request->status !== StockDispenseRequest::STATUS_PENDING
        && ($movementCount === ($request->status === StockDispenseRequest::STATUS_EXECUTED ? 1 : 0));

    return [
        'driver' => $driver,
        'workers' => $workers,
        'assertions' => [
            'successful_workers' => count($successes),
            'failed_workers' => count($workers) - count($successes),
            'final_request_status' => $request->status,
            'bom_stage' => $bom->fresh()->stage,
            'issue_movement_count' => $movementCount,
        ],
        'passed' => $passed,
    ];
}

/**
 * @return array<string, mixed>
 */
function runConcurrentApproveApprove(string $driver): array
{
    $context = seedDispenseContext();
    $service = app(StockDispenseRequestService::class);
    $technical = User::query()->findOrFail((int) $context['technical_user_id']);
    $admin = User::query()->findOrFail((int) $context['admin_user_id']);
    $request = $service->submit(
        Bom::query()->findOrFail((int) $context['bom_id']),
        $context['barcodes'],
        $technical,
    );

    $context['request_id'] = $request->id;
    $tmpDir = makeTmpDir('approve-approve');
    $workerScript = dirname(__DIR__).'/bin/verify-dispense-concurrency.php';

    $workers = spawnWorkers($workerScript, [
        ['approve', $context, $tmpDir.'/A.json'],
        ['approve', $context, $tmpDir.'/B.json'],
    ]);

    $request->refresh();
    $bom = Bom::query()->findOrFail((int) $context['bom_id']);
    $movementCount = StockMovement::query()
        ->where('reference_type', 'bom')
        ->where('reference_id', $bom->id)
        ->where('movement_type', StockMovement::TYPE_ISSUE)
        ->count();

    $successes = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'success'));
    $failures = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'rejected'));

    $passed = count($successes) === 1
        && count($failures) === 1
        && $request->status === StockDispenseRequest::STATUS_EXECUTED
        && $bom->fresh()->stage === Bom::STAGE_WIP
        && $movementCount === 1;

    return [
        'driver' => $driver,
        'workers' => $workers,
        'assertions' => [
            'successful_approves' => count($successes),
            'failed_approves' => count($failures),
            'final_request_status' => $request->status,
            'bom_stage' => $bom->fresh()->stage,
            'issue_movement_count' => $movementCount,
        ],
        'passed' => $passed,
    ];
}

/**
 * @return array{bom_id: int, technical_user_id: int, admin_user_id: int, barcodes: list<string>}
 */
function seedDispenseContext(): array
{
    config(['inventory.dispense_requires_approval' => true, 'workshop.enabled' => true]);
    app(PermissionCatalogService::class)->syncToDatabase();

    $operational = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    while (StockItem::query()->where('alt_codes', $operational)->exists()) {
        $operational = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    $barcode = StockItem::barcodeForOperationalCode($operational);

    StockItem::query()->create([
        'code' => 'ITM-'.$operational,
        'name' => "صنف {$operational}",
        'spec' => 'مواصفات',
        'store_class' => 'A',
        'is_quick_dispense' => false,
        'uom' => 'piece',
        'alt_codes' => $operational,
        'barcode' => StockItem::barcodeForOperationalCode($operational),
        'qty' => 20,
        'reserved' => 0,
        'price' => 100.00,
        'wac' => 100.00,
        'status' => 'ok',
        'last_moved_at' => now()->toDateString(),
    ]);

    $company = ContractCompany::query()->create([
        'company_code' => 'CO-MIL-'.uniqid(),
        'name' => 'جهة عسكرية',
        'is_military' => true,
    ]);
    ContractCompanyDebt::query()->create([
        'contract_company_id' => $company->id,
        'due' => 0,
        'collected' => 0,
        'status' => 'pending',
    ]);

    $patient = Patient::query()->create([
        'patient_code' => (string) random_int(100000, 999999),
        'patient_qr' => 'QR-'.uniqid(),
        'tracking_uid' => 'uid-'.uniqid(),
        'name' => 'مريض عسكري تزامن',
        'patient_type' => Patient::TYPE_MILITARY,
        'contract_company_id' => $company->id,
        'company_name' => $company->name,
        'registered_at' => now()->toDateString(),
        'status' => Patient::STATUS_ACTIVE,
    ]);

    $case = CaseRecord::query()->create([
        'case_no' => 'CASE-'.now()->year.'-'.random_int(1000, 9999),
        'order_ref' => str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'patient_id' => $patient->id,
        'contract_company_id' => $company->id,
        'company_name' => $company->name,
        'patient_type' => Patient::TYPE_MILITARY,
        'path' => CaseRecord::PATH_MILITARY,
        'stage_key' => CaseRecord::STAGE_MANUFACTURING,
        'manufacturing_stage' => CaseRecord::MFG_WAREHOUSE,
        'work_order_no' => 'WO-'.uniqid(),
    ]);

    $bom = app(BomService::class)->createSpecRaw($case, [
        ['stock_item_code' => $operational, 'name' => 'صنف خام', 'qty' => 1],
    ]);
    app(BomService::class)->reserveForCase($case->fresh());

    $workshopTech = seedWorkshopUser(Role::SLUG_WORKSHOP);
    $section = WorkshopSection::query()->create([
        'name' => 'قسم تزامن',
        'code' => 'conc-'.substr(uniqid(), -8),
        'sort' => 1,
        'active' => true,
    ]);
    $section->technicians()->sync([$workshopTech->id]);

    $assignment = app(WorkshopAssignmentService::class);
    $case = $assignment->assignOnApprove($case->fresh(), $section->id, $workshopTech->id);
    $assignment->approveAssignment($case);

    $technical = seedRoleUser(Role::SLUG_TECHNICAL);
    $admin = seedRoleUser(Role::SLUG_SUPER_ADMIN);

    return [
        'bom_id' => $bom->id,
        'technical_user_id' => $technical->id,
        'admin_user_id' => $admin->id,
        'barcodes' => [$barcode],
    ];
}

function seedRoleUser(string $roleSlug): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => $roleSlug],
        ['label_ar' => $roleSlug],
    );

    $ids = Permission::query()->where('dashboard', '!=', Role::SLUG_ADMIN)->pluck('id');
    $role->permissions()->syncWithoutDetaching($ids);

    return User::query()->updateOrCreate(
        ['username' => "conc_{$roleSlug}"],
        [
            'role_id' => $role->id,
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'name' => "مستخدم {$roleSlug}",
        ],
    );
}

function seedWorkshopUser(string $roleSlug): User
{
    return seedRoleUser($roleSlug);
}

function makeTmpDir(string $label): string
{
    $tmpDir = sys_get_temp_dir().'/dispense-concurrency-'.$label.'-'.getmypid();
    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0700, true);
    }

    return $tmpDir;
}

/**
 * @param  list<array{0: string, 1: array<string, mixed>, 2: string}>  $jobs
 * @return array<string, array<string, mixed>>
 */
function spawnWorkers(string $workerScript, array $jobs): array
{
    $phpBinary = PHP_BINARY;
    $env = array_merge($_ENV, $_SERVER);
    $env['APP_ENV'] = 'testing';

    $processes = [];
    $results = [];
    $contextPaths = [];

    foreach ($jobs as $index => [$mode, $context, $resultPath]) {
        $contextPath = dirname($resultPath).'/context-'.$index.'.json';
        file_put_contents($contextPath, json_encode($context, JSON_THROW_ON_ERROR));
        $contextPaths[$index] = $contextPath;
        @unlink($resultPath);

        $process = new Process(
            [$phpBinary, $workerScript, '--worker', $mode, $contextPath, $resultPath],
            dirname(__DIR__, 2),
            $env,
        );
        $process->setTimeout(120);
        $processes[$index] = $process;
    }

    foreach ($processes as $process) {
        $process->start();
    }

    foreach ($processes as $index => $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            fwrite(STDERR, "Worker {$index} failed: ".$process->getErrorOutput()."\n");
        }
        $resultPath = $jobs[$index][2];
        $results[(string) $index] = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
    }

    return [
        'A' => $results['0'] ?? $results[0],
        'B' => $results['1'] ?? $results[1],
    ];
}
