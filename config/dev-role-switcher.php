<?php

/**
 * أدوار شريط التبديل السريع — للعرض التوضيحي المحلي فقط.
 *
 * @return array<string, array{label: string, route: string}>
 */
return [
    'roles' => [
        'reception' => ['label' => 'الاستقبال', 'route' => 'reception.dashboard'],
        'doctor' => ['label' => 'الطبيب', 'route' => 'doctor.dashboard'],
        'spec' => ['label' => 'التوصيف', 'route' => 'spec.dashboard'],
        'adjustments' => ['label' => 'معدلات و تكاليف', 'route' => 'adjustments.dashboard'],
        'costing' => ['label' => 'الاعتماد', 'route' => 'costing.dashboard'],
        'operations' => ['label' => 'التشغيل', 'route' => 'operations.dashboard'],
        'cashier' => ['label' => 'الخزنة', 'route' => 'cashier.dashboard'],
        'workshop' => ['label' => 'قسم الإنتاج', 'route' => 'workshop.dashboard'],
        'technical' => ['label' => 'المخزن', 'route' => 'technical.dashboard'],
    ],
];
