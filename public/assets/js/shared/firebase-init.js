/**
 * تهيئة Firebase الأمامي (اختياري) — يعمل فقط إن مُلئت إعدادات الويب في .env.
 * يوفّر:
 *   window.getFcmToken()        → Promise<string|null>  (device_id)
 *   window.FcmForeground(cb)    → استقبال الإشعارات والتطبيق مفتوح
 */
(function () {
  var cfg = window.FIREBASE_WEB;
  if (!cfg || !window.firebase || !cfg.apiKey) {
    window.getFcmToken = function () { return Promise.resolve(null); };
    return;
  }

  try {
    firebase.initializeApp({
      apiKey: cfg.apiKey,
      authDomain: cfg.authDomain,
      projectId: cfg.projectId,
      messagingSenderId: cfg.messagingSenderId,
      appId: cfg.appId,
    });
  } catch (e) { /* مهيّأ مسبقاً */ }

  var messaging = null;
  try { messaging = firebase.messaging(); } catch (e) { messaging = null; }

  window.getFcmToken = function () {
    if (!messaging || !('Notification' in window)) return Promise.resolve(null);

    return Notification.requestPermission()
      .then(function (perm) {
        if (perm !== 'granted') return null;
        return navigator.serviceWorker
          .register('/firebase-messaging-sw.js')
          .then(function (reg) {
            return messaging.getToken({ vapidKey: cfg.vapidKey, serviceWorkerRegistration: reg });
          });
      })
      .catch(function () { return null; });
  };

  window.FcmForeground = function (cb) {
    if (!messaging) return;
    try { messaging.onMessage(cb); } catch (e) { /* صامت */ }
  };

  // تجديد التوكن داخل اللوحة وربطه بالمستخدم الحالي.
  if (messaging && window.location.pathname.indexOf('/login') === -1) {
    window.getFcmToken().then(function (token) {
      if (!token) return;
      var m = document.querySelector('meta[name="csrf-token"]');
      fetch('/devices', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': m ? m.getAttribute('content') : '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ device_id: token, device_type: 'web' }),
      }).catch(function () { /* صامت */ });
    });
  }
})();
