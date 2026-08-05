<?php

/**
 * قوالب مجموعات التوصيف — تربط الأطقم/المخصصات بمجموعة تظهر في التوصيف والمعدلات.
 * group_label في BOM = label (مثل «ركبة») وليس اسم الطقم.
 */
return [
    'groups' => [
        'knee' => [
            'label' => 'ركبة',
            'icon' => '🦵',
            'keywords' => ['ركبة', 'ركبه', 'knee', 'فخذ'],
            'default_type' => 'assembly',
        ],
        'foot' => [
            'label' => 'قدم',
            'icon' => '🦶',
            'keywords' => ['قدم', 'قدمين', 'foot', 'ساق'],
            'default_type' => 'assembly',
        ],
        'elbow' => [
            'label' => 'كوع',
            'icon' => '💪',
            'keywords' => ['كوع', 'elbow'],
            'default_type' => 'assembly',
        ],
        'hip' => [
            'label' => 'ورك',
            'icon' => '🦴',
            'keywords' => ['ورك', 'hip'],
            'default_type' => 'assembly',
        ],
        'shoulder' => [
            'label' => 'كتف',
            'icon' => '🫲',
            'keywords' => ['كتف', 'shoulder'],
            'default_type' => 'assembly',
        ],
        'accessories' => [
            'label' => 'مخصصات',
            'icon' => '🧩',
            'keywords' => ['مخصص', 'مخصصات', 'إكسسوار', 'accessory'],
            'default_type' => 'accessory',
        ],
        'general' => [
            'label' => 'عام',
            'icon' => '📦',
            'keywords' => [],
            'default_type' => 'assembly',
            'fallback' => true,
        ],
    ],
];
