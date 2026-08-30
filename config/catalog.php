<?php

/**
 * قالب الأصناف — ترويسات موحّدة للجدول، الاستيراد، والتقارير.
 *
 * غيّر ترتيب الأعمدة عبر template_column_order / table_column_order.
 */
return [
    /** أقصى عدد أصناف في كل قوائم البرنامج — الإدارة، المخزن، الإنتاج، التوصيف (0 = بدون حد). */
    'list_limit' => (int) env('CATALOG_LIST_LIMIT', 10000),

    /**
     * ترتيب أعمدة Excel (استيراد/تصدير/قالب).
     *
     * @var list<string>
     */
    'template_column_order' => [
        'code',
        'page_number',
        'name',
        'brand',
        'alt_codes',
        'uom',
        'opening_qty',
        'addition',
        'discount',
        'balance',
        'price',
    ],

    /**
     * ترتيب أعمدة جدول لوحة الإدارة (يشمل الأعمدة المحسوبة).
     *
     * @var list<string>
     */
    'table_column_order' => [
        'code',
        'page_number',
        'name',
        'brand',
        'alt_codes',
        'uom',
        'opening_qty',
        'addition',
        'discount',
        'catalog_balance',
        'warehouse_qty',
        'price',
    ],

    /** @var array<string, array{label: string, field: string, template?: bool, table?: bool, align?: string}> */
    'columns' => [
        'code' => ['label' => 'رقم الصنف', 'field' => 'code', 'template' => true, 'table' => true, 'align' => 'right'],
        'page_number' => ['label' => 'رقم الصفحة', 'field' => 'page_number', 'template' => true, 'table' => true, 'align' => 'center'],
        'name' => ['label' => 'اسم الصنف', 'field' => 'name', 'template' => true, 'table' => true, 'align' => 'right'],
        'brand' => ['label' => 'الماركة', 'field' => 'brand', 'template' => true, 'table' => true, 'align' => 'right'],
        'alt_codes' => ['label' => 'الأكواد', 'field' => 'alt_codes', 'template' => true, 'table' => true, 'align' => 'right'],
        'uom' => ['label' => 'الوحدة', 'field' => 'uom', 'template' => true, 'table' => true, 'align' => 'center'],
        'opening_qty' => ['label' => 'رصيد أول المده', 'field' => 'opening_qty', 'template' => true, 'table' => true, 'align' => 'center'],
        'addition' => ['label' => 'الاضافة', 'field' => 'addition', 'template' => true, 'table' => true, 'align' => 'center'],
        'discount' => ['label' => 'الخصم', 'field' => 'discount', 'template' => true, 'table' => true, 'align' => 'center'],
        'balance' => ['label' => 'الرصيد', 'field' => 'balance', 'template' => true, 'table' => false, 'align' => 'center'],
        'catalog_balance' => ['label' => 'رصيد كتالوج', 'field' => 'catalog_balance', 'template' => false, 'table' => true, 'align' => 'center'],
        'warehouse_qty' => ['label' => 'رصيد المخزن', 'field' => 'warehouse_qty', 'template' => false, 'table' => true, 'align' => 'center'],
        'price' => ['label' => 'السعر الأساسي', 'field' => 'price', 'template' => true, 'table' => true, 'align' => 'center'],
    ],

    /** @deprecated استخدم CatalogColumns::templateHeaders() */
    'template_headers' => [],

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
        'أكواد',
        'اكواد',
        'الاكواد',
        'الصنف',
        'القسم',
        'المورد',
        'سعر التكلفة',
        'أعلى سعر',
        'أسعار إضافية',
    ],
];
