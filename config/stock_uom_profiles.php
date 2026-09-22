<?php

/**
 * قوالب تحويل وحدة التوريد → وحدة المخزن (الصرف/الارتجاع).
 * يمكن للإدارة إضافة قوالب مخصصة عبر إعدادات وحدات القياس (تُخزَّن في settings).
 */
return [
    'profiles' => [
        'one_to_one' => [
            'label' => '1:1 — نفس وحدة المخزن',
            'supply_uom' => null,
            'units_per_supply_unit' => 1,
        ],
        'sheet_100x100_cm2' => [
            'label' => 'ورقة 100×100 سم → 10,000 سم²',
            'supply_uom' => 'ورقة',
            'units_per_supply_unit' => 10000,
            'base_uom_hint' => 'سم²',
        ],
        'sheet_50x50_cm2' => [
            'label' => 'ورقة 50×50 سم → 2,500 سم²',
            'supply_uom' => 'ورقة',
            'units_per_supply_unit' => 2500,
            'base_uom_hint' => 'سم²',
        ],
        'roll_per_kg' => [
            'label' => 'لفة → 1 كيلو (مثال أقمشة)',
            'supply_uom' => 'لفة',
            'units_per_supply_unit' => 1,
            'base_uom_hint' => 'كيلو',
        ],
        'pack_10_pieces' => [
            'label' => 'علبة → 10 قطع',
            'supply_uom' => 'علبة',
            'units_per_supply_unit' => 10,
            'base_uom_hint' => 'قطعة',
        ],
        'pack_100_pieces' => [
            'label' => 'باكة → 100 قطعة',
            'supply_uom' => 'باكة',
            'units_per_supply_unit' => 100,
            'base_uom_hint' => 'قطعة',
        ],
    ],

    'supply_unit_suggestions' => [
        'ورقة', 'لفة', 'علبة', 'باكة', 'كرتونة', 'طقم', 'عبوة',
    ],
];
