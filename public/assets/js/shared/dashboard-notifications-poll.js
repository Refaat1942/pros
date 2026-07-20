/**
 * إشعارات منبثقة في كل شاشة عبر Polling خفيف للسيرفر.
 *
 * - يستعلم عن /notifications/feed كل فترة قصيرة.
 * - عند وصول إشعار جديد: صوت + Toast + إشعار متصفح + تحديث عدّاد الجرس.
 * - ما دامت إشعارات غير مقروءة ولم يفتح المستخدم صفحة الإشعارات: تكرار الصوت كل N دقيقة (إعداد السوبر أدمن).
 */
(function () {
  'use strict';

  var FEED_URL = window.__NOTIF_FEED_URL || '/notifications/feed';
  var POLL_MS = 20000;
  var TOAST_ID = 'notifToast';
  var seen = null;
  var unreadCount = 0;
  var reminderTimer = null;
  var firstPollDone = false;

  function soundEnabled() {
    return window.__NOTIF_SOUND_ENABLED !== false;
  }

  function reminderMs() {
    var ms = parseInt(window.__NOTIF_REMINDER_MS, 10);
    return ms > 0 ? ms : 60000;
  }

  function isOnNotificationsPage() {
    return document.body.getAttribute('data-active-page') === 'notifications';
  }

  function shouldRemind() {
    return soundEnabled() && unreadCount > 0 && !isOnNotificationsPage();
  }

  function beep() {
    if (!soundEnabled()) return;
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

  function stopReminder() {
    if (reminderTimer) {
      clearInterval(reminderTimer);
      reminderTimer = null;
    }
  }

  function startReminder() {
    stopReminder();
    if (!shouldRemind()) return;
    reminderTimer = setInterval(function () {
      if (shouldRemind()) {
        beep();
      } else {
        stopReminder();
      }
    }, reminderMs());
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

  function applyFeedSettings(data) {
    if (typeof data.sound_enabled === 'boolean') {
      window.__NOTIF_SOUND_ENABLED = data.sound_enabled;
    }
    if (data.reminder_minutes) {
      window.__NOTIF_REMINDER_MS = Math.max(1, parseInt(data.reminder_minutes, 10) || 1) * 60000;
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

        applyFeedSettings(data);
        unreadCount = data.unread_count || 0;
        setBadge(unreadCount);

        var items = data.items || [];

        if (seen === null) {
          seen = {};
          items.forEach(function (it) { seen[it.id] = true; });
          firstPollDone = true;
          if (shouldRemind()) {
            beep();
            startReminder();
          }
          return;
        }

        var fresh = items.filter(function (it) { return !seen[it.id]; });
        fresh.reverse().forEach(function (it) {
          seen[it.id] = true;
          beep();
          toast(it.title, it.body);
          browserNotif(it.title, it.body);
        });
        items.forEach(function (it) { seen[it.id] = true; });

        if (firstPollDone) {
          if (shouldRemind()) {
            startReminder();
          } else {
            stopReminder();
          }
        }
      })
      .catch(function () { /* صامت */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    poll();
    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) poll();
    });
  });
})();
