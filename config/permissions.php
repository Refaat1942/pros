<?php

/**
 * كتالوج الإجراءات التفصيلية لكل لوحة تحكم.
 * صلاحيات العرض (views) تُولَّد تلقائياً من config/dashboards.php → {dashboard}.{page}.view
 */
return [
    'dashboard_labels' => [
        'reception' => ['label_ar' => 'لوحة الاستقبال', 'icon' => '📋'],
        'doctor' => ['label_ar' => 'لوحة الطبيب', 'icon' => '🩺'],
        'spec' => ['label_ar' => 'لوحة التوصيف', 'icon' => '📐'],
        'adjustments' => ['label_ar' => 'لوحة المعدلات والتكاليف', 'icon' => '📏'],
        'costing' => ['label_ar' => 'لوحة الاعتماد', 'icon' => '✅'],
        'operations' => ['label_ar' => 'مكتب التشغيل', 'icon' => '🎯'],
        'cashier' => ['label_ar' => 'الخزنة', 'icon' => '💵'],
        'workshop' => ['label_ar' => 'قسم الإنتاج', 'icon' => '🏭'],
        'technical' => ['label_ar' => 'لوحة المخزن', 'icon' => '📦'],
        'admin' => ['label_ar' => 'لوحة الإدارة', 'icon' => '⚙️'],
    ],

    'actions' => [
        'print-quote' => [
            'label_ar' => 'طباعة عرض السعر / الفاتورة',
            'dashboard' => 'reception',
        ],
        'skip-diagnosis' => [
            'label_ar' => 'تخطّي الكشف الطبي (الدفع المباشر للتوصيف)',
            'dashboard' => 'doctor',
        ],
        'view-costs' => [
            'label_ar' => 'التكلفة الداخلية (WAC) وهامش الربح',
            'dashboard' => 'costing',
        ],
        'view-military-profit' => [
            'label_ar' => 'نِسَب الربحية العسكرية',
            'dashboard' => 'admin',
        ],
        'view-prices' => [
            'label_ar' => 'أسعار البيع وعروض الأسعار',
            'dashboard' => 'admin',
        ],
        'view-revenue' => [
            'label_ar' => 'الإيرادات والمؤشرات المالية',
            'dashboard' => 'admin',
        ],
        'super-admin' => [
            'label_ar' => 'سوبر أدمن — صلاحية كاملة على النظام',
            'dashboard' => 'admin',
        ],
        'approve-pricing' => [
            'label_ar' => 'اعتماد التسعير في مكتب التشغيل',
            'dashboard' => 'operations',
        ],
        'confirm-cash-payment' => [
            'label_ar' => 'تأكيد استلام المبلغ في الخزنة',
            'dashboard' => 'cashier',
        ],
        'manage-inventory' => [
            'label_ar' => 'إدارة كتالوج الأصناف (إضافة/تعديل/حذف)',
            'dashboard' => 'admin',
        ],
        'manage-stock-kits' => [
            'label_ar' => 'إدارة الأطقم الجاهزة والمخصصات',
            'dashboard' => 'admin',
        ],
        'import-inventory' => [
            'label_ar' => 'الرفع الجماعي للأصناف (Excel/CSV)',
            'dashboard' => 'admin',
        ],
        'view-inventory-overview' => [
            'label_ar' => 'لوحة المخزن التفصيلية',
            'dashboard' => 'admin',
        ],
        'print-barcode' => [
            'label_ar' => 'طباعة باركود الأصناف الحراري',
            'dashboard' => 'admin',
        ],
        'manage-permissions' => [
            'label_ar' => 'إدارة مصفوفة الصلاحيات',
            'dashboard' => 'admin',
        ],
        'manage-workshop-sections' => [
            'label_ar' => 'إدارة أقسام الإنتاج وربط الفنيين',
            'dashboard' => 'admin',
        ],
        'view-workshop-tracking' => [
            'label_ar' => 'لوحة تتبع أوامر الشغل في قسم الإنتاج',
            'dashboard' => 'admin',
        ],
        'approve-dispense' => [
            'label_ar' => 'اعتماد طلبات صرف المخزن',
            'dashboard' => 'admin',
        ],
        'approve-services' => [
            'label_ar' => 'تصديق إدارة الخدمات — مسار عسكري',
            'dashboard' => 'admin',
        ],
    ],

    /**
     * إجراءات مرتبطة بصفحة إدارية — منح {dashboard}.{page}.view يكفي لفتح الشاشة وتشغيلها.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    'page_action_aliases' => [
        'view-workshop-tracking' => ['admin', 'workshop-tracking'],
        'manage-workshop-sections' => ['admin', 'workshop-sections'],
        'approve-dispense' => ['admin', 'dispense-approvals'],
        'approve-services' => ['admin', 'services-approvals'],
        'manage-permissions' => ['admin', 'permissions'],
        'view-inventory-overview' => ['admin', 'inventory-overview'],
        'manage-inventory' => ['admin', 'catalog'],
        'manage-stock-kits' => ['admin', 'stock-kits'],
    ],

    /**
     * الإسناد الافتراضي للإجراءات عند تشغيل الـ seeder (عدا صلاحيات العرض).
     */
    'default_actions' => [
        'reception' => ['print-quote'],
        'doctor' => ['skip-diagnosis'],
        'costing' => ['view-costs'],
        'operations' => ['approve-pricing', 'view-costs', 'print-quote'],
        'cashier' => ['confirm-cash-payment', 'print-quote'],
        'technical' => [],
        'spec' => [],
        'adjustments' => [],
        'admin' => [
            'view-prices',
            'view-revenue',
            'view-costs',
            'view-military-profit',
            'manage-permissions',
            'manage-workshop-sections',
            'view-workshop-tracking',
            'approve-dispense',
            'approve-services',
            'super-admin',
        ],
    ],
];
