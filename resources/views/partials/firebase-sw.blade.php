@php
    $enabled = (bool) config('firebase.enabled')
        && ! empty($cfg['apiKey'])
        && ! empty($cfg['appId'])
        && ! empty($cfg['messagingSenderId']);
@endphp
// Firebase Messaging Service Worker — مُولّد من إعدادات .env
@if ($enabled)
importScripts('{{ url('assets/vendor/firebase-app-compat.js') }}');
importScripts('{{ url('assets/vendor/firebase-messaging-compat.js') }}');

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
// Firebase معطّل أو غير مُعد — يُستخدم Polling + الصوت داخل اللوحة.
self.addEventListener('install', function () { self.skipWaiting(); });
@endif
