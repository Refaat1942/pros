<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-1: قيد فريد (case_id, installment_no) على المدفوعات — دفاع في العمق ضد
 * التحصيل المزدوج (بالإضافة إلى قفل صف الحالة في CashierPaymentService).
 *
 * سياسة السلامة المالية (Correction A):
 *   - لا تُعدَّل ولا تُحذف أي بيانات مالية تلقائياً أثناء الهجرة.
 *   - إن وُجدت أقساط مكررة تُوقَف الهجرة بخطأ واضح يذكر case_id ورقم القسط
 *     وأرقام/معرّفات الدفعات المتعارضة، ليصلحها المشغّل يدوياً عبر أمر مخصّص
 *     (prosthetics:remediate-duplicate-installments) قبل إعادة تشغيل الهجرة.
 *   - القيد الفريد يُنشَأ فقط بعد التأكد من نظافة البيانات.
 *
 * التوافق: PostgreSQL (VPS + Offline LAN) و SQLite (اختبارات) — Schema builder فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateInstallments();

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['case_id', 'installment_no'], 'payments_case_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_case_installment_unique');
        });
    }

    /**
     * يكشف الأقساط المكررة ويُوقِف الهجرة بخطأ تفصيلي دون تعديل أي بيانات.
     */
    private function assertNoDuplicateInstallments(): void
    {
        $duplicates = DB::table('payments')
            ->select('case_id', 'installment_no', DB::raw('COUNT(*) as dup_count'))
            ->groupBy('case_id', 'installment_no')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('case_id')
            ->orderBy('installment_no')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $lines = [];

        foreach ($duplicates as $dup) {
            $payments = DB::table('payments')
                ->where('case_id', $dup->case_id)
                ->where('installment_no', $dup->installment_no)
                ->orderBy('id')
                ->get(['id', 'payment_no', 'amount', 'received_at']);

            $refs = $payments
                ->map(fn ($p) => "#{$p->id} ({$p->payment_no}, amount={$p->amount})")
                ->implode(', ');

            $lines[] = "  - case_id={$dup->case_id}, installment_no={$dup->installment_no} → {$dup->dup_count} صفوف: {$refs}";
        }

        $report = implode("\n", $lines);

        throw new RuntimeException(
            "الهجرة أُوقِفت: توجد أقساط مدفوعات مكررة (case_id, installment_no) — لن تُعدَّل أي بيانات مالية تلقائياً.\n".
            "أصلِح البيانات يدوياً ثم أعد تشغيل الهجرة. للمراجعة/الإصلاح المتعمّد استخدم:\n".
            "  php artisan prosthetics:remediate-duplicate-installments          # عرض فقط (dry-run)\n".
            "  php artisan prosthetics:remediate-duplicate-installments --apply   # تطبيق بعد المراجعة\n\n".
            "التكرارات المكتشفة:\n".$report
        );
    }
};
