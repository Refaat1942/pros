<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use App\Support\Journeys\JourneyStepFailed;
use App\Support\StockQuantity;
use Database\Seeders\MilitaryRankSeeder;
use Database\Seeders\VisitTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * حالات تجريبية كاملة (جهة متعاقدة / غير متعاقدة / نقدي / عسكري / خدمات عسكرية) من الاستقبال حتى التسليم،
 * بنفس مسارات الشاشات — ويطبع أين توقفت أي حالة ولماذا.
 */
class DemoJourneysCommand extends Command
{
    protected $signature = 'prosthetics:demo-journeys
                            {--items= : أكواد الأصناف بالكمية، مثال: 1S101:1,617S7=3:0.5 (افتراضياً يُختار صنف بالعدد وصنف بالكسر من المخزن)}
                            {--dry-run : تجربة كاملة ثم التراجع عن كل شيء — لا يبقى أي أثر في قاعدة البيانات}
                            {--seed : يضيف ما ينقص من البيانات الأساسية: أقسام إنتاج بفنييها، الرتب، أنواع الزيارات}
                            {--print : يفتح كل مستندات الطباعة لكل حالة ويطبع روابطها}
                            {--times=1 : عدد مرات تكرار المسارات الخمسة}';

    protected $description = 'Run one demo case per patient pathway from reception to delivery and report where any step fails';

    public function handle(): int
    {
        $lines = $this->resolveLines();
        if ($lines === null) {
            return self::FAILURE;
        }

        $this->info('الأصناف المستخدمة:');
        foreach ($lines as $line) {
            $this->line('  • '.$line['item']->pickerCode().' — '.$line['item']->name.' × '.StockQuantity::format($line['qty'], $line['item']->uom));
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            DB::beginTransaction();
        }
        if ($this->option('seed')) {
            $this->seedMasterData();
        }
        if ($dryRun) {
            $this->ensureTemporaryWorkshopSection();
        }

        $runner = new CaseJourneyRunner($lines);
        $times = max(1, (int) $this->option('times'));
        $failed = 0;
        $delivered = [];

        try {
            for ($round = 1; $round <= $times; $round++) {
                foreach (CaseJourneyRunner::journeys() as $journey) {
                    $label = CaseJourneyRunner::label($journey);
                    $this->newLine();
                    $this->info("▶ {$label}".($times > 1 ? " ({$round}/{$times})" : ''));
                    $logStart = count($runner->log());

                    try {
                        $case = $runner->run($journey, 'حالة تجريبية — '.$label);
                        $this->printSteps($runner, $logStart);
                        $this->info("  ✔ تم التسليم — الحالة {$case->case_no}");
                        $delivered[] = $case;

                        if ($this->option('print')) {
                            foreach ($runner->printDocuments($case) as $doc) {
                                $doc['ok']
                                    ? $this->line("    🖨 {$doc['label']}: ".url($doc['path']))
                                    : $this->error("    ✗ {$doc['label']}: {$doc['detail']} — ".url($doc['path']));
                                $failed += $doc['ok'] ? 0 : 1;
                            }
                        }
                    } catch (JourneyStepFailed $e) {
                        $failed++;
                        $this->printSteps($runner, $logStart);
                    }
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        $this->newLine();
        $total = count(CaseJourneyRunner::journeys()) * $times;
        $summary = count($delivered)." من {$total} حالات وصلت للتسليم".($dryRun ? ' — تجربة فقط، لم يُحفظ شيء.' : '.');
        $failed === 0 ? $this->info($summary) : $this->error($summary);

        if (! $dryRun) {
            $this->line('لمسح الحالات التجريبية وإرجاع ما صُرف للمخزن: php artisan prosthetics:purge-patient-data (يمسح كل المرضى).');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** بيانات أساسية لا تُكرَّر: يُضاف فقط ما ليس موجوداً. */
    private function seedMasterData(): void
    {
        app(MilitaryRankSeeder::class)->run();
        app(VisitTypeSeeder::class)->run();

        $sections = [
            ['code' => 'lower', 'name' => 'قسم الأطراف السفلية', 'technicians' => ['فني أطراف سفلية 1', 'فني أطراف سفلية 2']],
            ['code' => 'upper', 'name' => 'قسم الأطراف العلوية', 'technicians' => ['فني أطراف علوية 1', 'فني أطراف علوية 2']],
            ['code' => 'orthotics', 'name' => 'قسم الجبائر والأحزمة', 'technicians' => ['فني جبائر 1', 'فني جبائر 2']],
        ];
        $workshopRoleId = Role::query()->where('slug', Role::SLUG_WORKSHOP)->value('id');

        foreach ($sections as $index => $data) {
            $section = WorkshopSection::query()->firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'sort' => ($index + 1) * 10, 'active' => true],
            );

            foreach ($data['technicians'] as $n => $name) {
                $technician = User::query()->firstOrCreate(
                    ['username' => "tech-{$data['code']}-".($n + 1)],
                    [
                        'name' => $name,
                        // الفني يظهر في قوائم التخصيص فقط — كلمة سر عشوائية، يغيّرها المدير لو احتاج دخوله.
                        'password' => Str::random(32),
                        'role_id' => $workshopRoleId,
                        'status' => User::STATUS_ACTIVE,
                    ],
                );
                $section->technicians()->syncWithoutDetaching([$technician->id]);
            }
        }

        $this->info('البيانات الأساسية جاهزة: '.WorkshopSection::query()->where('active', true)->count().' قسم إنتاج، '
            .User::query()->where('role_id', $workshopRoleId)->count().' فني، الرتب وأنواع الزيارات.');
    }

    /** التجربة فقط: قسم وفني مؤقتان إن لم يوجد قسم به فني — يُتراجع عنهما مع باقي التجربة. */
    private function ensureTemporaryWorkshopSection(): void
    {
        $hasAssignee = WorkshopSection::query()->where('active', true)->whereHas('technicians')->exists();
        if ($hasAssignee) {
            return;
        }

        $technician = User::query()->create([
            'name' => 'فني تجريبي',
            'username' => 'demo-tech-'.Str::lower(Str::random(6)),
            'password' => Str::random(32),
            'role_id' => Role::query()->where('slug', Role::SLUG_WORKSHOP)->value('id'),
            'status' => User::STATUS_ACTIVE,
        ]);

        WorkshopSection::query()->create(['name' => 'قسم تجريبي', 'code' => 'demo', 'sort' => 99, 'active' => true])
            ->technicians()->attach($technician->id);

        $this->warn('لا يوجد قسم إنتاج به فني — أُضيف قسم وفني مؤقتان للتجربة فقط.');
    }

    private function printSteps(CaseJourneyRunner $runner, int $from): void
    {
        foreach (array_slice($runner->log(), $from) as $step) {
            $step['ok']
                ? $this->line("  ✓ {$step['step']}")
                : $this->error("  ✗ {$step['step']} — {$step['detail']}");
        }
    }

    /** @return list<array{item: StockItem, qty: float}>|null */
    private function resolveLines(): ?array
    {
        $option = trim((string) $this->option('items'));

        if ($option !== '') {
            $lines = [];
            foreach (array_filter(array_map('trim', explode(',', $option))) as $entry) {
                $separator = strrpos($entry, ':');
                $code = $separator === false ? $entry : substr($entry, 0, $separator);
                $qty = $separator === false ? 1.0 : (float) substr($entry, $separator + 1);
                $item = StockItem::findByOperationalCode($code);

                if ($item === null || $qty <= 0) {
                    $this->error("الصنف «{$code}» غير موجود أو الكمية غير صالحة.");

                    return null;
                }

                $lines[] = ['item' => $item, 'qty' => $qty];
            }

            return $lines;
        }

        $candidates = StockItem::query()
            ->whereNotNull('barcode')
            ->where('price', '>', 0)
            ->whereRaw('qty - reserved >= ?', [10])
            ->orderBy('id')
            ->limit(500)
            ->get();

        $whole = $candidates->first(fn (StockItem $i) => ! StockQuantity::isFractionalUom($i->uom));
        $fraction = $candidates->first(fn (StockItem $i) => StockQuantity::isFractionalUom($i->uom));

        $lines = array_values(array_filter([
            $whole ? ['item' => $whole, 'qty' => 1.0] : null,
            $fraction ? ['item' => $fraction, 'qty' => 0.5] : null,
        ]));

        if ($lines === []) {
            $this->error('لا يوجد صنف بباركود وسعر ورصيد كافٍ (10 على الأقل) — حدّد الأصناف بـ --items=الكود:الكمية');

            return null;
        }

        return $lines;
    }
}
