<?php

return [

    /*
    |--------------------------------------------------------------------------
    | تفعيل إشعارات Firebase (FCM)
    |--------------------------------------------------------------------------
    | عند false: النظام يحفظ الإشعارات داخلياً ويظهرها في كل لوحة (Polling + صوت)
    | لكن لا يرسل Push عبر FCM. مفيد للتطوير/الاختبار دون اتصال بالشبكة.
    */
    'enabled' => env('FIREBASE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | بيانات حساب الخدمة (Admin SDK) — سرية، خارج الريبو
    |--------------------------------------------------------------------------
    | المسار لملف service-account JSON. الملف مُستثنى في .gitignore.
    */
    'credentials' => env('FIREBASE_CREDENTIALS', base_path('firebase/threeth-firebase-adminsdk-fbsvc-66271fdcd8.json')),

    'project_id' => env('FIREBASE_PROJECT_ID', 'threeth'),

    /*
    |--------------------------------------------------------------------------
    | إعدادات الويب (للواجهة الأمامية — Firebase JS SDK)
    |--------------------------------------------------------------------------
    | هذه ليست سرية (تُرسل للمتصفح). املأها من إعدادات مشروع Firebase ▸ Web App
    | حتى يستطيع المتصفح استخراج device_id (FCM token) وتشغيل الإشعارات.
    | إن تُركت فارغة، يعمل النظام بآلية Polling + صوت داخلي كبديل.
    */
    'web' => [
        'apiKey'            => env('FIREBASE_WEB_API_KEY', ''),
        'authDomain'        => env('FIREBASE_WEB_AUTH_DOMAIN', ''),
        'projectId'         => env('FIREBASE_PROJECT_ID', 'threeth'),
        'messagingSenderId' => env('FIREBASE_WEB_SENDER_ID', ''),
        'appId'             => env('FIREBASE_WEB_APP_ID', ''),
        'vapidKey'          => env('FIREBASE_WEB_VAPID_KEY', ''),
    ],
];
