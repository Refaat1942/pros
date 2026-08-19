<?php

/**
 * قوائم الأصناف حسب اللوحة — الأعمدة المتاحة وافتراضيات كل دور.
 *
 * يُتحكَّم في الإظهار الفعلي من لوحة «صلاحيات قوائم الأصناف» (CatalogListVisibilityService).
 */
return [
    /**
     * أقسام الإدارة — مفتاح رئيسي يفعّل/يوقف كل قوائم القسم لكل دور.
     *
     * @var array<string, array{label_ar: string, default_roles: list<string>, profiles: list<string>}>
     */
    'sections' => [
        'inventory_supply' => [
            'label_ar' => 'المخزون والتوريد — قوائم الأصناف',
            'default_roles' => ['super_admin', 'admin'],
            'profiles' => ['admin_catalog', 'inventory_overview', 'stock_kits_picker'],
        ],
    ],

    /** @var array<string, array{label_ar: string, dashboard: string, page: string, section?: string, default_roles: list<string>, default_columns: list<string>}> */
    'profiles' => [
        'admin_catalog' => [
            'label_ar' => 'جدول الأصناف والأسعار',
            'dashboard' => 'admin',
            'page' => 'catalog',
            'section' => 'inventory_supply',
            'default_roles' => ['super_admin', 'admin'],
            'default_columns' => [
                'code', 'page_number', 'name', 'brand', 'alt_codes', 'uom',
                'opening_qty', 'addition', 'discount', 'catalog_balance', 'warehouse_qty', 'price',
            ],
        ],
        'inventory_overview' => [
            'label_ar' => 'متابعة حركة الأصناف',
            'dashboard' => 'admin',
            'page' => 'inventory-overview',
            'section' => 'inventory_supply',
            'default_roles' => ['super_admin', 'admin'],
            'default_columns' => [
                'code', 'name', 'brand', 'category', 'qty', 'reserved', 'available',
                'backorder', 'price', 'wac', 'expiry', 'price_history', 'print',
            ],
        ],
        'stock_kits_picker' => [
            'label_ar' => 'بحث الأصناف — الأطقم الجاهزة',
            'dashboard' => 'admin',
            'page' => 'stock-kits',
            'section' => 'inventory_supply',
            'default_roles' => ['super_admin', 'admin'],
            'default_columns' => ['code', 'name', 'brand', 'page_number', 'alt_codes', 'uom'],
        ],
        'technical_inventory' => [
            'label_ar' => 'جدول توفر المخزن — المخزن',
            'dashboard' => 'technical',
            'page' => 'inventory',
            'default_roles' => ['technical'],
            'default_columns' => ['code', 'name', 'brand', 'uom', 'available', 'status'],
        ],
        'technical_bom_items' => [
            'label_ar' => 'بنود قائمة الصرف — قسم الإنتاج',
            'dashboard' => 'technical',
            'page' => 'bom',
            'default_roles' => ['technical'],
            'default_columns' => ['code', 'name', 'brand', 'qty', 'uom', 'issued_qty', 'returned_qty'],
        ],
        'spec_picker' => [
            'label_ar' => 'قائمة اختيار الأصناف — التوصيف',
            'dashboard' => 'spec',
            'page' => 'spec',
            'default_roles' => ['spec'],
            'default_columns' => ['code', 'name', 'brand', 'uom', 'available'],
        ],
        'adjustments_picker' => [
            'label_ar' => 'قائمة اختيار الأصناف — المعدلات',
            'dashboard' => 'adjustments',
            'page' => 'adjustments',
            'default_roles' => ['adjustments'],
            'default_columns' => ['code', 'name', 'brand', 'uom', 'available', 'qty'],
        ],
        'doctor_picker' => [
            'label_ar' => 'قائمة توصيات الطبيب',
            'dashboard' => 'doctor',
            'page' => 'queue',
            'default_roles' => ['doctor'],
            'default_columns' => ['code', 'name', 'brand', 'uom'],
        ],
    ],

    /**
     * أعمدة الملفات غير جدول الإدارة (admin_catalog يستخدم config/catalog.php).
     *
     * @var array<string, array<string, array{label: string, gate?: string}>>
     */
    'profile_columns' => [
        'inventory_overview' => [
            'code' => ['label' => 'الكود / الباركود'],
            'name' => ['label' => 'الصنف'],
            'brand' => ['label' => 'الماركة'],
            'category' => ['label' => 'القسم'],
            'qty' => ['label' => 'الرصيد'],
            'reserved' => ['label' => 'محجوز'],
            'available' => ['label' => 'متاح'],
            'backorder' => ['label' => 'طلب توريد'],
            'price' => ['label' => 'السعر', 'gate' => 'view-prices'],
            'wac' => ['label' => 'WAC', 'gate' => 'view-costs'],
            'expiry' => ['label' => 'الصلاحية'],
            'price_history' => ['label' => 'آخر الأسعار', 'gate' => 'view-prices'],
            'print' => ['label' => 'طباعة', 'gate' => 'print-barcode'],
        ],
        'technical_inventory' => [
            'code' => ['label' => 'كود الصنف'],
            'name' => ['label' => 'اسم الصنف'],
            'brand' => ['label' => 'الماركة'],
            'uom' => ['label' => 'الوحدة'],
            'available' => ['label' => 'الرصيد المتاح'],
            'status' => ['label' => 'الحالة'],
            'qty' => ['label' => 'رصيد المخزن'],
            'reserved' => ['label' => 'محجوز'],
            'category' => ['label' => 'القسم'],
        ],
        'technical_bom_items' => [
            'code' => ['label' => 'الكود'],
            'name' => ['label' => 'الصنف'],
            'brand' => ['label' => 'الماركة'],
            'qty' => ['label' => 'المطلوب'],
            'uom' => ['label' => 'الوحدة'],
            'issued_qty' => ['label' => 'المصروف'],
            'returned_qty' => ['label' => 'المرتجع'],
            'unit_cost' => ['label' => 'تكلفة الوحدة', 'gate' => 'view-costs'],
        ],
        'spec_picker' => [
            'code' => ['label' => 'كود الصنف'],
            'name' => ['label' => 'اسم الصنف'],
            'brand' => ['label' => 'الماركة'],
            'uom' => ['label' => 'الوحدة'],
            'available' => ['label' => 'متاح'],
            'qty' => ['label' => 'الرصيد'],
        ],
        'adjustments_picker' => [
            'code' => ['label' => 'كود الصنف'],
            'name' => ['label' => 'اسم الصنف'],
            'brand' => ['label' => 'الماركة'],
            'uom' => ['label' => 'الوحدة'],
            'available' => ['label' => 'متاح'],
            'qty' => ['label' => 'الرصيد'],
        ],
        'doctor_picker' => [
            'code' => ['label' => 'كود الصنف'],
            'name' => ['label' => 'اسم الصنف'],
            'brand' => ['label' => 'الماركة'],
            'uom' => ['label' => 'الوحدة'],
        ],
        'stock_kits_picker' => [
            'code' => ['label' => 'كود الصنف'],
            'name' => ['label' => 'اسم الصنف'],
            'brand' => ['label' => 'الماركة'],
            'page_number' => ['label' => 'رقم الصفحة'],
            'alt_codes' => ['label' => 'أكواد بديلة'],
            'uom' => ['label' => 'الوحدة'],
        ],
    ],

    /** أعمدة admin_catalog — بوابات إضافية فوق تعريف catalog.php */
    'admin_catalog_gates' => [
        'price' => 'view-prices',
        'opening_qty' => 'view-inventory-overview',
        'addition' => 'view-inventory-overview',
        'discount' => 'view-inventory-overview',
        'catalog_balance' => 'view-inventory-overview',
        'warehouse_qty' => 'view-inventory-overview',
    ],

    /** أعمدة إلزامية عند تفعيل القائمة */
    'required_columns' => [
        'admin_catalog' => ['code', 'name'],
        'inventory_overview' => ['code', 'name'],
        'stock_kits_picker' => ['code', 'name'],
        'technical_inventory' => ['code', 'name'],
        'technical_bom_items' => ['code', 'name'],
        'spec_picker' => ['code', 'name'],
        'adjustments_picker' => ['code', 'name'],
        'doctor_picker' => ['code', 'name'],
    ],

    /** @var array<string, array<string, list<string>>> */
    'item_field_map' => [
        'inventory_overview' => [
            'code' => ['code'],
            'name' => ['name'],
            'brand' => ['brand'],
            'category' => ['category'],
            'qty' => ['qty'],
            'reserved' => ['reserved'],
            'available' => ['available'],
            'backorder' => ['backorder'],
            'price' => ['price'],
            'wac' => ['wac'],
            'expiry' => ['expiry'],
        ],
        'technical_inventory' => [
            'code' => ['code'],
            'name' => ['name'],
            'brand' => ['brand'],
            'uom' => ['uom'],
            'available' => ['available'],
            'status' => ['status'],
            'qty' => ['qty'],
            'reserved' => ['reserved'],
            'category' => ['category'],
        ],
        'technical_bom_items' => [
            'code' => ['stock_item_code', 'code'],
            'name' => ['name'],
            'brand' => ['brand'],
            'qty' => ['qty'],
            'uom' => ['uom'],
            'issued_qty' => ['issued_qty'],
            'returned_qty' => ['returned_qty'],
            'unit_cost' => ['unit_cost'],
        ],
        'stock_kits_picker' => [
            'code' => ['code', 'catalog_number'],
            'name' => ['name'],
            'brand' => ['brand'],
            'page_number' => ['page_number'],
            'alt_codes' => ['alt_codes'],
            'uom' => ['uom'],
        ],
    ],
];
