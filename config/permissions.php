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
        'adjustments' => ['label_ar' => 'لوحة المعدلات', 'icon' => '📏'],
        'costing' => ['label_ar' => 'لوحة التكاليف', 'icon' => '💰'],
        'operations' => ['label_ar' => 'مكتب التشغيل', 'icon' => '🎯'],
        'cashier' => ['label_ar' => 'الخزنة', 'icon' => '💵'],
        'workshop' => ['label_ar' => 'ورشة التصنيع', 'icon' => '🏭'],
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
            'label_ar' => 'إدارة أقسام الورشة وربط الفنيين',
            'dashboard' => 'admin',
        ],
        'view-workshop-tracking' => [
            'label_ar' => 'لوحة تتبع أوامر الشغل في الورشة',
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
