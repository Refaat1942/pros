<?php

/**
 * أدوار شريط التبديل السريع — للعرض التوضيحي المحلي فقط.
 *
 * @return array<string, array{label: string, route: string}>
 */
return [
    'roles' => [
        'reception'   => ['label' => 'الاستقبال', 'route' => 'reception.dashboard'],
        'doctor'      => ['label' => 'الطبيب', 'route' => 'doctor.dashboard'],
        'spec'        => ['label' => 'التوصيف', 'route' => 'spec.dashboard'],
        'adjustments' => ['label' => 'المعدلات', 'route' => 'adjustments.dashboard'],
        'costing'     => ['label' => 'التكاليف', 'route' => 'costing.dashboard'],
        'operations'  => ['label' => 'التشغيل', 'route' => 'operations.dashboard'],
        'technical'   => ['label' => 'المخزن', 'route' => 'technical.dashboard'],
    ],
];
