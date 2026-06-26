/**
 * Dashboard toast — SweetAlert-style alerts, top-left, auto-dismiss.
 */
(function (global) {
  'use strict';

  var DEFAULT_MS = 5000;
  var timers = {};
  var ICONS = {
    success:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>',
    error:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
    warning:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
    info:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  };
  var TITLES = {
    success: 'تم بنجاح',
    error: 'تنبيه',
    warning: 'تحذير',
    info: 'إشعار',
  };

  function timerId(el) {
    return el.id || el.getAttribute('data-toast-id') || 'toast-anonymous';
  }

  function clearTimer(el) {
    var id = timerId(el);
    if (timers[id]) {
      clearTimeout(timers[id]);
      delete timers[id];
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function resolveType(options) {
    if (options.type) return options.type;
    if (options.isError) return 'error';
    var prefix = options.prefix;
    if (prefix && String(prefix).indexOf('🔔') >= 0) return 'info';
    if (prefix && String(prefix).indexOf('⚠') >= 0) return 'warning';
    return 'success';
  }

  function bindClose(el) {
    var btn = el.querySelector('.toast__close');
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
      hide(el);
    });
  }

  function defaultRender(el, msg, options) {
    var type = resolveType(options);
    var title = options.title || TITLES[type] || TITLES.success;
    var duration = options.duration || DEFAULT_MS;

    el.className = 'toast toast--' + type;
    el.style.setProperty('--toast-duration', (duration / 1000) + 's');
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');

    el.innerHTML =
      '<div class="toast__inner">' +
        '<div class="toast__icon" aria-hidden="true">' + (ICONS[type] || ICONS.success) + '</div>' +
        '<div class="toast__body">' +
          '<div class="toast__title">' + escapeHtml(title) + '</div>' +
          '<div class="toast__message">' + escapeHtml(msg) + '</div>' +
        '</div>' +
        '<button type="button" class="toast__close" aria-label="إغلاق">&times;</button>' +
      '</div>' +
      '<div class="toast__progress" aria-hidden="true"></div>';

    bindClose(el);
  }

  function hide(el) {
    if (!el) return;
    clearTimer(el);

    if (el.classList.contains('flash-message')) {
      el.style.transition = 'opacity 0.35s ease';
      el.style.opacity = '0';
      setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
      }, 350);
      return;
    }

    el.classList.remove('show');
    el.classList.add('hidden');
  }

  function show(msg, options) {
    options = options || {};
    var el = options.element || document.getElementById(options.id || 'toast');
    if (!el) return;

    clearTimer(el);

    if (typeof options.render === 'function') {
      options.render(el, msg, options);
    } else if (options.html) {
      el.innerHTML = options.html;
      el.className = 'toast show';
    } else {
      defaultRender(el, msg, options);
    }

    el.classList.remove('hidden');
    el.classList.add('show');

    var duration = options.duration || DEFAULT_MS;
    timers[timerId(el)] = setTimeout(function () {
      hide(el);
    }, duration);
  }

  function initFlashMessages() {
    document.querySelectorAll('.flash-message[role="alert"]').forEach(function (el) {
      timers['flash-' + timerId(el)] = setTimeout(function () {
        hide(el);
      }, DEFAULT_MS);
    });
  }

  global.DashboardToast = {
    show: show,
    hide: hide,
    duration: DEFAULT_MS,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlashMessages);
  } else {
    initFlashMessages();
  }
})(window);
