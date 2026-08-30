<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح بيانات تاريخي — كميات دفعات الأسعار وأسعار بنود طلبات التسعير.
 *
 * H-8: مستقلّة عن كود التطبيق (لا تستدعي app(Service)). على قاعدة جديدة لا توجد
 * صفوف مطابقة فيصبح تنفيذها بلا أثر — فلا تنكسر migrate مع أي تغيير مستقبلي في
 * الخدمات. أما التصحيح الفعلي للأسعار على القواعد القائمة فيُجرى عبر أمر مخصّص
 * (prosthetics:backfill-pricing) عند الحاجة، حفاظاً على تجميد منطق الهجرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        // إصلاح كميات دفعات الأسعار صفر/فارغة → 1 (استعلام خام، مجمّد زمنياً).
        DB::table('stock_item_prices')
            ->where('amount', '>', 0)
            ->where(function ($q) {
                $q->whereNull('qty')->orWhere('qty', '<=', 0);
            })
            ->update(['qty' => 1]);
    }

    public function down(): void
    {
        // irreversible data correction
    }
};
