<?php

namespace App\Console\Commands;

use App\Models\PricingRequest;
use App\Services\PricingService;
use Illuminate\Console\Command;

/**
 * إعادة حساب أسعار بنود طلبات التسعير القديمة التي لم تُسعَّر (unit_price صفر/فارغ).
 *
 * H-8: نُقِل هذا المنطق من هجرة تصحيح بيانات إلى أمر مستقل حتى لا ترتبط سجلّات
 * الهجرة بكود الخدمات. يُشغَّل يدوياً على القواعد القائمة عند الحاجة. dry-run افتراضي.
 */
class BackfillPricingCommand extends Command
{
    protected $signature = 'prosthetics:backfill-pricing {--apply : تنفيذ إعادة الحساب فعلياً}';

    protected $description = 'إعادة حساب أسعار بنود طلبات التسعير غير المُسعّرة (تصحيح بيانات تاريخي)';

    public function handle(PricingService $pricingService): int
    {
        $query = PricingRequest::query()
            ->where('computed_total', '<=', 0)
            ->whereHas('items', function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('unit_price')->orWhere('unit_price', '<=', 0);
                });
            })
            ->orderBy('id');

        $count = $query->count();

        if ($count === 0) {
            $this->info('لا توجد طلبات تسعير تحتاج إعادة حساب.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn("👁️  عرض فقط: {$count} طلب تسعير سيُعاد حسابه. أضِف --apply للتنفيذ.");

            return self::SUCCESS;
        }

        $done = 0;
        $query->each(function (PricingRequest $request) use ($pricingService, &$done) {
            $pricingService->refreshLinePrices($request);
            $done++;
        });

        $this->info("✅ تم إعادة حساب {$done} طلب تسعير.");

        return self::SUCCESS;
    }
}
