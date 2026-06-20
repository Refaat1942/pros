<?php

namespace Database\Seeders;

use App\Models\VisitType;
use Illuminate\Database\Seeder;

/**
 * أنواع الزيارة الافتراضية — تغطي مسار الاستقبال في مركز الأطراف الصناعية.
 */
class VisitTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'كشف أولي',              // بداية المسار — تحويل للعيادة والتوصيف
            'متابعة طبية',           // مراجعة الطبيب بعد الكشف الأول
            'تجربة تركيب',           // قياسات وتجربة أولى/ثانية (فني التعديلات)
            'تعديل وصيانة',          // ضبط الطرف أو صيانة دورية
            'تسليم الطرف',           // استلام نهائي ومسح QR الختامي
            'مراجعة ما بعد التسليم', // متابعة رضا المريض وجودة الطرف
        ];

        foreach ($types as $name) {
            VisitType::query()->updateOrCreate(
                ['name' => $name],
                [],
            );
        }
    }
}
