/**
 * عرض إشعارات FCM داخل اللوحة (Push فقط — بلا أي Polling للسيرفر).
 *
 * يعتمد كلياً على Firebase Cloud Messaging:
 *   - والتطبيق مفتوح (foreground): يصدر صوتاً + Toast + إشعار متصفح.
 *   - والتطبيق مغلق/بالخلفية: يتكفّل به service worker (firebase-messaging-sw.js).
 *
 * لا يوجد أي استعلام دوري — لا حِمل على الخادم.
 */
(function () {
  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime);
      osc.frequency.setValueAtTime(1180, ctx.currentTime + 0.12);
      gain.gain.setValueAtTime(0.001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.32);
      osc.start();
      osc.stop(ctx.currentTime + 0.34);
    } catch (e) { /* صامت */ }
  }

  function toast(msg) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: '🔔 ' });
      return;
    }
    var el = document.getElementById('toast');
    if (el) {
      el.textContent = '🔔 ' + msg;
      el.className = 'toast visible';
      setTimeout(function () { el.classList.remove('visible'); }, 5000);
    }
  }

  function show(payload) {
    var n = (payload && payload.notification) || {};
    var title = n.title || 'إشعار جديد';
    var body = n.body || '';

    beep();
    toast(title + (body ? ' — ' + body : ''));

    if ('Notification' in window && Notification.permission === 'granted') {
      try { new Notification(title, { body: body, dir: 'rtl', lang: 'ar', icon: '/favicon.ico' }); } catch (e) { /* صامت */ }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // يُفعّل مستقبل FCM في المقدمة (يُعرّفه firebase-init.js عند اكتمال إعداد الويب).
    if (window.FcmForeground) {
      window.FcmForeground(show);
    }
  });
})();
