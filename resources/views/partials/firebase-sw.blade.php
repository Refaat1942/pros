@php $enabled = ! empty($cfg['apiKey']) && ! empty($cfg['appId']) && ! empty($cfg['messagingSenderId']); @endphp
// Firebase Messaging Service Worker — مُولّد من إعدادات .env
@if ($enabled)
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: {!! json_encode($cfg['apiKey']) !!},
    authDomain: {!! json_encode($cfg['authDomain']) !!},
    projectId: {!! json_encode($cfg['projectId']) !!},
    messagingSenderId: {!! json_encode($cfg['messagingSenderId']) !!},
    appId: {!! json_encode($cfg['appId']) !!}
});

var messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
    var n = (payload && payload.notification) || {};
    self.registration.showNotification(n.title || 'إشعار جديد', {
        body: n.body || '',
        icon: '/favicon.ico',
        dir: 'rtl',
        lang: 'ar'
    });
});
@else
// إعدادات Firebase الأمامية غير مكتملة — لا عمل (يُستخدم بديل Polling + الصوت).
self.addEventListener('install', function () { self.skipWaiting(); });
@endif
