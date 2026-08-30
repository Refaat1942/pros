<?php

/**
 * الصفحات الافتراضية لموظف القسم (غير المدير) — عند access_tier = department_staff.
 * المفتاح = slug الدور (نفس slug لوحة التحكم).
 *
 * @var array<string, list<string>>
 */
return [
    'reception' => ['appointments', 'delivery', 'notifications'],
    'doctor' => ['queue', 'notifications'],
    'spec' => ['spec', 'catalog', 'notifications'],
    'adjustments' => ['adjustments', 'catalog', 'notifications'],
    'costing' => ['costing', 'notifications'],
    'operations' => ['pending', 'notifications'],
    'cashier' => ['payments', 'notifications'],
    'workshop' => ['workshop', 'notifications'],
    'technical' => ['bom', 'catalog', 'notifications'],
];
