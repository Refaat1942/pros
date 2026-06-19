/**
 * Dashboard toast + flash alerts — auto-dismiss after 5 seconds.
 */
(function (global) {
  'use strict';

  var DEFAULT_MS = 5000;
  var timers = {};

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
    if (!el.classList.contains('toast')) {
      el.classList.add('hidden');
    }
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
    } else {
      var prefix = options.isError ? '⚠️ ' : (options.prefix !== undefined ? options.prefix : '✅ ');
      el.textContent = prefix + msg;
    }

    el.classList.remove('hidden');
    el.classList.add('show');

    timers[timerId(el)] = setTimeout(function () {
      hide(el);
    }, options.duration || DEFAULT_MS);
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
