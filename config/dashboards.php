<?php

/**
 * إعدادات لوحات التحكم — مستخرجة من prototype HTML (عناوين + أصول فقط)
 * Guard concept (تصميم فقط — غير مفعّل):
 *   reception → guard:reception
 *   doctor      → guard:doctor
 *   spec        → guard:spec
 *   adjustments → guard:adjustments
 *   operations  → guard:operations
 *   technical   → guard:technical
 *   admin       → guard:admin
 */
return [
    'home' => [
        'title' => 'نظام إدارة مركز إنتاج الأطراف الصناعية',
        'layout' => 'layouts.app',
        'styles' => ['assets/css/index.css'],
        'scripts' => ['assets/js/pages/index.js'],
        'body_attributes' => '',
        'guard' => null,
    ],
    'reception' => [
        'title' => 'لوحة موظف الاستقبال — مركز الأطراف الصناعية',
        'layout' => 'layouts.reception',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/reception-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/pages/reception-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => '',
        'guard' => 'reception',
    ],
    'doctor' => [
        'title' => 'لوحة الطبيب المعالج — مركز الأطراف الصناعية',
        'layout' => 'layouts.doctor',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/doctor-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/stock-multi-select.js',
            'assets/js/pages/doctor-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => '',
        'guard' => 'doctor',
    ],
    'spec' => [
        'title' => 'لوحة التوصيف الفني — مركز الأطراف الصناعية',
        'layout' => 'layouts.spec',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/technical-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/shared/operations-desk.js',
            'assets/js/shared/inventory-returns.js',
            'assets/js/pages/technical-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => 'data-dashboard="spec"',
        'guard' => 'spec',
    ],
    'adjustments' => [
        'title' => 'لوحة المعدلات — مركز الأطراف الصناعية',
        'layout' => 'layouts.adjustments',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/technical-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/shared/operations-desk.js',
            'assets/js/shared/inventory-returns.js',
            'assets/js/pages/technical-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => 'data-dashboard="adjustments"',
        'guard' => 'adjustments',
    ],
    'operations' => [
        'title' => 'لوحة التشغيل — مركز الأطراف الصناعية',
        'layout' => 'layouts.operations',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/technical-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/shared/operations-desk.js',
            'assets/js/shared/inventory-returns.js',
            'assets/js/pages/technical-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => 'data-dashboard="operations"',
        'guard' => 'operations',
    ],
    'technical' => [
        'title' => 'لوحة المخزون — مركز الأطراف الصناعية',
        'layout' => 'layouts.technical',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/technical-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/shared/operations-desk.js',
            'assets/js/shared/inventory-returns.js',
            'assets/js/pages/technical-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => 'data-dashboard="inventory"',
        'guard' => 'technical',
    ],
    'admin' => [
        'title' => 'لوحة إدارة النظام — مركز الأطراف الصناعية',
        'layout' => 'layouts.admin',
        'styles' => ['assets/css/dashboard-mobile.css', 'assets/css/admin-dashboard.css'],
        'scripts' => [
            'assets/js/shared/export-kit.js',
            'assets/js/shared/charts-kit.js',
            'assets/js/shared/stock-catalog.js',
            'assets/js/shared/cases-workflow.js',
            'assets/js/shared/pricing-queue.js',
            'assets/js/shared/bom-inventory.js',
            'assets/js/shared/operations-desk.js',
            'assets/js/shared/credit-notes.js',
            'assets/js/pages/admin-dashboard.js',
            'assets/js/shared/dashboard-mobile.js',
        ],
        'body_attributes' => '',
        'guard' => 'admin',
    ],
];
