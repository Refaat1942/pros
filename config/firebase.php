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
    'credentials' => env('FIREBASE_CREDENTIALS', base_path('firebase/prosthetics-4857b-firebase-adminsdk.json')),

    'project_id' => env('FIREBASE_PROJECT_ID', 'prosthetics-4857b'),

    /*
    |--------------------------------------------------------------------------
    | إعدادات الويب (للواجهة الأمامية — Firebase JS SDK)
    |--------------------------------------------------------------------------
    | هذه ليست سرية (تُرسل للمتصفح). املأها من إعدادات مشروع Firebase ▸ Web App
    | حتى يستطيع المتصفح استخراج device_id (FCM token) وتشغيل الإشعارات.
    | إن تُركت فارغة، يعمل النظام بآلية Polling + صوت داخلي كبديل.
    */
    'web' => [
        'apiKey' => env('FIREBASE_WEB_API_KEY', 'AIzaSyBJ52S6mZGRAt_AM3GhF0EJBBEIUlvoedM'),
        'authDomain' => env('FIREBASE_WEB_AUTH_DOMAIN', 'prosthetics-4857b.firebaseapp.com'),
        'projectId' => env('FIREBASE_PROJECT_ID', 'prosthetics-4857b'),
        'messagingSenderId' => env('FIREBASE_WEB_SENDER_ID', '600182139349'),
        'appId' => env('FIREBASE_WEB_APP_ID', '1:600182139349:web:743111e5ef170b70d7355c'),
        'vapidKey' => env('FIREBASE_WEB_VAPID_KEY', 'BLOJqy1yBbLUbVQCvbdAi-uwBd6CNogE_hOPPWXy__Dnj8yfF8U4Ujujl8tPrCFNoFHeFag9Yfqc9_JBebI46V8'),
    ],
];
