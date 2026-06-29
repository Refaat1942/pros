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
            'كشف أولي',
            'متابعة طبية',
            'تجربة تركيب',
            'تعديل وصيانة',
            'تسليم الطرف',
            'مراجعة ما بعد التسليم',
        ];

        foreach ($types as $index => $name) {
            VisitType::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => ($index + 1) * 10],
            );
        }
    }
}
