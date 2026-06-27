/**
 * صفحة إحصائيات الاستقبال — ChartKit من بيانات السيرفر فقط.
 */
(function () {
  'use strict';

  function initReceptionStatistics() {
    var root = document.getElementById('receptionStatsRoot');
    var dataEl = document.getElementById('receptionStatsData');

    if (!root || !dataEl || typeof ChartKit === 'undefined') {
      return;
    }

    var payload;

    try {
      payload = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
      console.error('reception-statistics: invalid JSON', err);
      return;
    }

    if (!payload.stats && !payload.charts) {
      return;
    }

    ChartKit.mount('receptionStatsRoot', {
      stats: payload.stats || [],
      charts: payload.charts || [],
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReceptionStatistics);
  } else {
    initReceptionStatistics();
  }
})();
