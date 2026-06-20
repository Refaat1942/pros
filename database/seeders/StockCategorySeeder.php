<?php

namespace Database\Seeders;

use App\Models\StockCategory;
use Illuminate\Database\Seeder;

class StockCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'مفاصل',
            'أقدام',
            'بطانات',
            'محولات',
            'إكسسوارات',
        ];

        foreach ($categories as $name) {
            StockCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
