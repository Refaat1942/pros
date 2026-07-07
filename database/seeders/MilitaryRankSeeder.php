<?php

namespace Database\Seeders;

use App\Models\MilitaryRank;
use Illuminate\Database\Seeder;

/**
 * الرتب العسكرية الافتراضية — كود الرتبة اختياري (nullable).
 */
class MilitaryRankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['name' => 'جندي',       'rank_code' => null,  'sort_order' => 10],
            ['name' => 'عريف',       'rank_code' => null,  'sort_order' => 20],
            ['name' => 'رقيب',       'rank_code' => 'SGT',  'sort_order' => 30],
            ['name' => 'رقيب أول',   'rank_code' => 'SSG',  'sort_order' => 40],
            ['name' => 'ملازم',      'rank_code' => '2LT',  'sort_order' => 50],
            ['name' => 'ملازم أول',  'rank_code' => '1LT',  'sort_order' => 60],
            ['name' => 'نقيب',       'rank_code' => 'CAPT', 'sort_order' => 70],
            ['name' => 'رائد',       'rank_code' => 'MAJ',  'sort_order' => 80],
            ['name' => 'مقدم',       'rank_code' => 'LTC',  'sort_order' => 90],
            ['name' => 'عقيد',       'rank_code' => 'COL',  'sort_order' => 100],
            ['name' => 'عميد',       'rank_code' => 'BG',   'sort_order' => 110],
            ['name' => 'لواء',       'rank_code' => 'MG',   'sort_order' => 120],
            ['name' => 'فريق',       'rank_code' => 'LTG',  'sort_order' => 130],
            ['name' => 'فريق أول',   'rank_code' => null,  'sort_order' => 140],
        ];

        foreach ($ranks as $row) {
            MilitaryRank::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'rank_code' => $row['rank_code'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
