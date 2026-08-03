<?php

/**
 * قالب الأصناف — ترويسات موحّدة للجدول، الاستيراد، والتقارير.
 *
 * الترتيب: رقم الصنف | رقم الصفحة | اسم الصنف | الأكواد | الوحدة |
 *          رصيد أول المده | الاضافة | الخصم | الرصيد
 */
return [
    'template_headers' => [
        'رقم الصنف',
        'رقم الصفحة',
        'اسم الصنف',
        'الأكواد',
        'الوحدة',
        'رصيد أول المده',
        'الاضافة',
        'الخصم',
        'الرصيد',
    ],

    /** @var array<string, array{label: string, field: string}> */
    'columns' => [
        'code' => ['label' => 'رقم الصنف', 'field' => 'code'],
        'page_number' => ['label' => 'رقم الصفحة', 'field' => 'page_number'],
        'name' => ['label' => 'اسم الصنف', 'field' => 'name'],
        'alt_codes' => ['label' => 'الأكواد', 'field' => 'alt_codes'],
        'uom' => ['label' => 'الوحدة', 'field' => 'uom'],
        'opening_qty' => ['label' => 'رصيد أول المده', 'field' => 'opening_qty'],
        'addition' => ['label' => 'الاضافة', 'field' => 'addition'],
        'discount' => ['label' => 'الخصم', 'field' => 'discount'],
        'catalog_balance' => ['label' => 'رصيد كتالوج', 'field' => 'catalog_balance'],
        'warehouse_qty' => ['label' => 'رصيد المخزن', 'field' => 'warehouse_qty'],
        'balance' => ['label' => 'الرصيد', 'field' => 'balance'],
    ],

    /** ترويسات قديمة — توافق خلفي عند الاستيراد. */
    'legacy_header_aliases' => [
        'كود الصنف',
        'اسم الصنف',
        'الكمية',
        'الحد الأدنى للصنف',
        'الحد الأدنى للطلب',
        'الحد الأدنى',
        'السعر',
        'الكود',
        'الصنف',
        'القسم',
        'المورد',
        'سعر التكلفة',
        'أعلى سعر',
        'أسعار إضافية',
    ],
];
