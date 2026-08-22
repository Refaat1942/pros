<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-1: دفاع في العمق ضد التحصيل المزدوج.
     *
     * قفل صف الحالة في CashierPaymentService يمنع التسلسل المزدوج، وهذا القيد
     * يضمن على مستوى قاعدة البيانات ألا يوجد رقمَا قسط متطابقان لنفس الحالة —
     * فأي سباق يتجاوز القفل يُرفض بخطأ فريد بدل إنشاء دفعة مكررة.
     *
     * متوافق مع PostgreSQL (VPS/LAN) و SQLite (الاختبارات).
     */
    public function up(): void
    {
        // تنظيف أي تكرارات تاريخية قبل فرض القيد (آمن: لا يحذف صفوفاً فريدة).
        // لا ينفَّذ أي حذف إن لم توجد تكرارات.
        $this->deduplicateExistingInstallments();

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
     * يعيد ترقيم الأقساط المكررة (إن وُجدت) قبل فرض القيد الفريد — لا يحذف أي دفعة.
     */
    private function deduplicateExistingInstallments(): void
    {
        $duplicates = DB::table('payments')
            ->select('case_id', 'installment_no')
            ->groupBy('case_id', 'installment_no')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('payments')
                ->where('case_id', $dup->case_id)
                ->where('installment_no', $dup->installment_no)
                ->orderBy('id')
                ->pluck('id');

            // نُبقي الأول كما هو ونعيد ترقيم الباقي لأعلى رقم قسط متاح.
            $maxInstallment = (int) DB::table('payments')
                ->where('case_id', $dup->case_id)
                ->max('installment_no');

            foreach ($rows->slice(1) as $id) {
                $maxInstallment++;
                DB::table('payments')->where('id', $id)->update(['installment_no' => $maxInstallment]);
            }
        }
    }
};
