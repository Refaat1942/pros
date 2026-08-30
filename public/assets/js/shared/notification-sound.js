/**
 * صوت إشعار أوضح وأعلى — نغمات صاعدة ثلاثية عبر Web Audio API.
 * يُستخدم من dashboard-notifications.js و dashboard-notifications-poll.js
 */
(function () {
  'use strict';

  var sharedCtx = null;

  function getContext() {
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!sharedCtx || sharedCtx.state === 'closed') {
      sharedCtx = new Ctx();
    }
    if (sharedCtx.state === 'suspended') {
      sharedCtx.resume().catch(function () { /* صامت */ });
    }
    return sharedCtx;
  }

  /**
   * @param {AudioContext} ctx
   * @param {number} freq
   * @param {number} start
   * @param {number} duration
   * @param {number} volume
   * @param {OscillatorType} [type]
   */
  function playTone(ctx, freq, start, duration, volume, type) {
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = type || 'triangle';
    osc.frequency.setValueAtTime(freq, start);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(Math.max(volume, 0.0001), start + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.03);
  }

  function playNotificationSound() {
    try {
      var ctx = getContext();
      if (!ctx) return;

      var t = ctx.currentTime;
      var mainVol = 0.78;

      // ثلاث نغمات صاعدة — واضحة ومميزة
      playTone(ctx, 880, t, 0.2, mainVol);
      playTone(ctx, 1174, t + 0.17, 0.2, mainVol);
      playTone(ctx, 1568, t + 0.34, 0.32, mainVol);

      // طبقة هارمونية خفيفة لزيادة الوضوح
      playTone(ctx, 1760, t + 0.34, 0.26, mainVol * 0.4, 'sine');
    } catch (e) { /* صامت */ }
  }

  window.NotificationSound = {
    play: playNotificationSound,
  };
})();
