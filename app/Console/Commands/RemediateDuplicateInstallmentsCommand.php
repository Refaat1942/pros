<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * إصلاح متعمّد للأقساط المكررة (case_id, installment_no) — تشغيل يدوي فقط.
 *
 * السلامة المالية:
 *   - افتراضياً «عرض فقط» (dry-run) — يشرح بالضبط ماذا سيتغيّر قبل أي تعديل.
 *   - لا يحذف أي دفعة، ولا يغيّر: amount, received_at, payment_no, method.
 *   - الإصلاح الوحيد المسموح: إعادة ترقيم installment_no للصفوف المكررة إلى أرقام
 *     شاغرة (يحفظ كامل التاريخ المالي — كل الدفعات تبقى).
 *   - كل تطبيق يُسجَّل في سجل الرقابة (append-only).
 *
 * لا يُستدعى أبداً تلقائياً من الهجرات.
 */
class RemediateDuplicateInstallmentsCommand extends Command
{
    protected $signature = 'prosthetics:remediate-duplicate-installments
                            {--apply : تطبيق إعادة الترقيم فعلياً (بدونه: عرض فقط)}';

    protected $description = 'كشف/إصلاح الأقساط المكررة (case_id, installment_no) بإعادة ترقيم آمنة — يدوي فقط، لا يحذف بيانات مالية';

    public function handle(): int
    {
        $duplicates = DB::table('payments')
            ->select('case_id', 'installment_no', DB::raw('COUNT(*) as dup_count'))
            ->groupBy('case_id', 'installment_no')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('case_id')
            ->orderBy('installment_no')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('لا توجد أقساط مكررة — البيانات نظيفة. يمكن تشغيل الهجرة بأمان.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');

        $this->warn($apply
            ? '⚙️  وضع التطبيق: ستُعاد ترقيم الأقساط المكررة (بدون حذف/تعديل مبالغ).'
            : '👁️  وضع العرض فقط (dry-run): لن تتغيّر أي بيانات. أضِف --apply للتنفيذ.');
        $this->newLine();

        $plan = [];

        foreach ($duplicates as $dup) {
            $rows = DB::table('payments')
                ->where('case_id', $dup->case_id)
                ->where('installment_no', $dup->installment_no)
                ->orderBy('id')
                ->get(['id', 'payment_no', 'amount', 'received_at']);

            // نُبقي أقدم صف (id الأصغر) على رقمه، ونعيد ترقيم الباقي لأرقام شاغرة.
            $keep = $rows->first();
            $reassign = $rows->slice(1);

            $maxInstallment = (int) DB::table('payments')
                ->where('case_id', $dup->case_id)
                ->max('installment_no');

            foreach ($reassign as $row) {
                $maxInstallment++;
                $plan[] = [
                    'case_id' => $dup->case_id,
                    'payment_id' => $row->id,
                    'payment_no' => $row->payment_no,
                    'from' => $dup->installment_no,
                    'to' => $maxInstallment,
                    'amount' => $row->amount,
                ];
            }

            $this->line("case_id={$dup->case_id} · القسط {$dup->installment_no} مكرر {$dup->dup_count}× — يبقى #{$keep->id} ({$keep->payment_no})");
        }

        $this->newLine();
        $this->table(
            ['case_id', 'payment_id', 'payment_no', 'from → to', 'amount (لا يتغيّر)'],
            array_map(fn ($p) => [
                $p['case_id'], $p['payment_id'], $p['payment_no'],
                "{$p['from']} → {$p['to']}", $p['amount'],
            ], $plan),
        );

        if (! $apply) {
            $this->newLine();
            $this->info('عرض فقط — لم تتغيّر أي بيانات. للتنفيذ بعد المراجعة: --apply');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $p) {
                DB::table('payments')
                    ->where('id', $p['payment_id'])
                    ->update(['installment_no' => $p['to']]);
            }
        });

        AuditService::log(
            action: 'remediate',
            description: 'إعادة ترقيم أقساط مدفوعات مكررة (بدون حذف/تعديل مبالغ) — إصلاح متعمّد',
            tag: 'financial',
            after: ['reassignments' => $plan, 'count' => count($plan)],
        );

        $this->newLine();
        $this->info('✅ تم إعادة الترقيم بأمان — كل الدفعات محفوظة، والمبالغ لم تتغيّر. أعد تشغيل الهجرة الآن.');

        return self::SUCCESS;
    }
}
