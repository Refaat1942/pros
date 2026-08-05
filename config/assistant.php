<?php

/**
 * إعدادات المساعد الذكي — مصادر تتحدّث تلقائياً عند تغيير ملفات الإعداد.
 *
 * - صفحات dashboards.php: تُولَّد من AssistantCatalogService
 * - sources: مدخلات تُبنى من مسارات config عند كل طلب
 */
return [
    /**
     * مصادر معرفة تُقرأ من config وتُدمج تلقائياً.
     * path = dot notation داخل config()
     */
    'sources' => [
        'catalog.template_headers' => [
            'dashboard' => 'admin',
            'page' => 'catalog',
            'title' => 'قالب Excel — الأصناف والأسعار',
            'keywords' => ['excel', 'xlsx', 'قالب', 'استيراد', 'تصدير', 'رفع', 'import', 'export', 'template'],
            'intro' => 'قالب رفع الأصناف يتكوّن من {count} أعمدة (تتحدّث تلقائياً مع الإعداد):',
        ],
        'catalog.list_limit' => [
            'dashboard' => 'admin',
            'page' => 'catalog',
            'title' => 'حد عرض الكتالوج',
            'keywords' => ['حد', 'limit', 'كتalog', 'عدد الاصناف'],
            'intro' => 'أقصى عدد أصناف يُعرض في لوحة الإدارة والتصدير: {value} (من إعداد CATALOG_LIST_LIMIT).',
        ],
    ],

    /** قوالب النص الافتراضي لصفحات بدون شرح مفصّل في knowledge.php */
    'page_templates' => [
        'answer' => '«{label}» — {title}. ضمن {dash_label}{group_suffix}. افتحها من القائمة الجانبية.',
        'steps' => [
            'من لوحة {dash_label} اختَر «{label}»{group_hint}.',
            'نفّذ المطلوب في الشاشة ثم احفظ.',
            'لو فيه زر طباعة 🖨️ — يطبع الورقة الرسمية.',
        ],
    ],
];
