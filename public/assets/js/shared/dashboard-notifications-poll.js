/**
 * إشعارات منبثقة في كل شاشة عبر Polling خفيف للسيرفر.
 *
 * يعمل بدون Firebase (مناسب للنظام المحلي/Offline):
 *   - يستعلم عن /notifications/feed كل فترة قصيرة.
 *   - عند وصول إشعار جديد للدور الحالي: صوت + Toast + إشعار متصفح + تحديث عدّاد الجرس.
 *   - أول استعلام يضبط الأساس فقط (لا يُظهر توست للقديم).
 */
(function () {
  'use strict';

  var FEED_URL = window.__NOTIF_FEED_URL || '/notifications/feed';
  var POLL_MS = 20000;
  var TOAST_ID = 'notifToast';
  var seen = null; // خريطة معرّفات معروفة؛ null حتى أول استعلام

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

  function toast(title, body) {
    var msg = body ? (title + ' — ' + body) : title;
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: TOAST_ID, type: 'info', title: 'إشعار جديد', duration: 7000 });
      return;
    }
    var el = document.getElementById(TOAST_ID);
    if (el) {
      el.textContent = '🔔 ' + msg;
      el.className = 'toast show';
      setTimeout(function () { el.classList.remove('show'); el.classList.add('hidden'); }, 7000);
    }
  }

  function browserNotif(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
      try { new Notification(title, { body: body || '', dir: 'rtl', lang: 'ar', icon: '/favicon.ico' }); } catch (e) { /* صامت */ }
    }
  }

  function setBadge(count) {
    var bell = document.getElementById('headerNotifBell');
    if (!bell) return;
    var badge = document.getElementById('headerNotifBadge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'dashboard-notif-badge';
      badge.id = 'headerNotifBadge';
      bell.appendChild(badge);
    }
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = false;
      badge.classList.remove('is-hidden');
      bell.setAttribute('aria-label', 'الإشعارات — ' + count + ' غير مقروء');
    } else {
      badge.textContent = '0';
      badge.hidden = true;
      badge.classList.add('is-hidden');
      bell.setAttribute('aria-label', 'الإشعارات');
    }
  }

  function poll() {
    fetch(FEED_URL, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        setBadge(data.unread_count || 0);

        var items = data.items || [];

        if (seen === null) {
          // أول تحميل: نتذكّر غير المقروء الحالي بدون إظهار توست.
          seen = {};
          items.forEach(function (it) { seen[it.id] = true; });
          return;
        }

        // العناصر الجديدة التي لم تُعرض بعد — من الأقدم للأحدث.
        var fresh = items.filter(function (it) { return !seen[it.id]; });
        fresh.reverse().forEach(function (it) {
          seen[it.id] = true;
          beep();
          toast(it.title, it.body);
          browserNotif(it.title, it.body);
        });
        items.forEach(function (it) { seen[it.id] = true; });
      })
      .catch(function () { /* صامت — لا نُزعج المستخدم عند انقطاع مؤقت */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    poll();
    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) poll();
    });
  });
})();
