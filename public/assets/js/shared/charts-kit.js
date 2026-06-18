/**
 * ChartKit — إحصائيات و Charts مشتركة (CSS فقط، بدون مكتبات خارجية)
 */
var ChartKit = (function () {
  var injected = false;

  function injectStyles() {
    if (injected) return;
    injected = true;
    var css = document.createElement('style');
    css.textContent = [
      '.ck-analytics { margin-bottom: 20px; }',
      '.ck-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 16px; }',
      '.ck-stat { background: var(--card, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }',
      '.ck-stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }',
      '.ck-stat-label { font-size: 11px; color: var(--text-muted, #64748b); font-weight: 600; }',
      '.ck-stat-value { font-size: 1.35rem; font-weight: 800; color: var(--secondary, #1e3a5f); line-height: 1.2; }',
      '.ck-stat-sub { font-size: 10px; color: var(--text-muted, #64748b); margin-top: 2px; }',
      '.ck-charts { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }',
      '.ck-chart-card.ck-wide { grid-column: 1 / -1; }',
      '@media (max-width: 768px) { .ck-charts { grid-template-columns: 1fr; } .ck-chart-card.ck-wide { grid-column: 1; } .ck-stats { grid-template-columns: 1fr 1fr; } }',
      '.ck-chart-card { background: var(--card, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }',
      '.ck-chart-card h4 { font-size: 13px; font-weight: 700; color: var(--secondary, #1e3a5f); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }',
      '.ck-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; font-size: 12px; }',
      '.ck-bar-row:last-child { margin-bottom: 0; }',
      '.ck-bar-label { min-width: 90px; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-muted, #64748b); font-weight: 500; }',
      '.ck-chart-card.ck-wide .ck-bar-label { min-width: 200px; max-width: 48%; white-space: normal; overflow: visible; text-overflow: unset; line-height: 1.4; font-weight: 600; color: var(--secondary, #1e3a5f); }',
      '.ck-chart-card.ck-wide .ck-bar-val { min-width: 88px; font-size: 12px; white-space: nowrap; }',
      '.ck-chart-card.ck-wide .ck-bar-row { align-items: center; gap: 12px; margin-bottom: 11px; }',
      '.ck-bar-track { flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }',
      '.ck-bar-fill { height: 100%; border-radius: 4px; transition: width 0.4s ease; }',
      '.ck-bar-val { min-width: 36px; text-align: left; font-weight: 700; font-size: 11px; color: var(--secondary, #1e3a5f); }',
      '.ck-donut-wrap { display: flex; align-items: center; gap: 16px; }',
      '.ck-donut-wrap.ck-donut-lg { justify-content: center; gap: 28px; padding: 16px 12px; min-height: 150px; }',
      '.ck-donut { width: 88px; height: 88px; border-radius: 50%; flex-shrink: 0; position: relative; }',
      '.ck-donut-lg .ck-donut { width: 112px; height: 112px; }',
      '.ck-donut-hole { position: absolute; inset: 14px; background: var(--card, #fff); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; color: var(--secondary, #1e3a5f); }',
      '.ck-donut-lg .ck-donut-hole { inset: 18px; font-size: 18px; }',
      '.ck-legend { flex: 1; font-size: 11px; }',
      '.ck-donut-lg .ck-legend { font-size: 13px; }',
      '.ck-legend-item { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }',
      '.ck-donut-lg .ck-legend-item { margin-bottom: 10px; padding: 6px 8px; background: #f8fafc; border-radius: 8px; }',
      '.ck-legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }',
      '.ck-donut-lg .ck-legend-dot { width: 10px; height: 10px; }',
      '.ck-legend-label { flex: 1; color: var(--text-muted, #64748b); font-weight: 600; }',
      '.ck-legend-val { font-weight: 700; color: var(--secondary, #1e3a5f); }',
      '.ck-donut-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border, #e2e8f0); }',
      '.ck-donut-summary-item { text-align: center; padding: 10px 8px; background: #f8fafc; border-radius: 10px; border: 1px solid #f1f5f9; }',
      '.ck-donut-summary-label { font-size: 10px; color: var(--text-muted, #64748b); font-weight: 600; margin-bottom: 4px; }',
      '.ck-donut-summary-value { font-size: 13px; font-weight: 800; color: var(--secondary, #1e3a5f); }',
      '.ck-spark-wrap { display: flex; align-items: flex-end; gap: 8px; }',
      '.ck-spark-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; }',
      '.ck-spark-bar-wrap { width: 100%; height: 56px; display: flex; align-items: flex-end; justify-content: center; }',
      '.ck-spark-bar { width: 72%; max-width: 40px; border-radius: 4px 4px 0 0; min-height: 4px; transition: height 0.3s; }',
      '.ck-spark-label { font-size: 11px; font-weight: 700; color: var(--secondary, #1e3a5f); margin-top: 8px; text-align: center; line-height: 1.35; width: 100%; }',
      '.ck-column-chart { display: flex; flex-direction: row-reverse; gap: 12px; margin-top: 4px; }',
      '.ck-column-yaxis { display: flex; flex-direction: column; justify-content: space-between; height: 232px; padding-bottom: 52px; font-size: 11px; color: var(--text-muted, #64748b); font-weight: 700; min-width: 44px; text-align: right; flex-shrink: 0; }',
      '.ck-column-area { flex: 1; min-width: 0; position: relative; }',
      '.ck-column-grid { position: absolute; left: 0; right: 0; top: 0; bottom: 52px; display: flex; flex-direction: column; justify-content: space-between; pointer-events: none; }',
      '.ck-column-gridline { border-top: 1px dashed #e2e8f0; width: 100%; }',
      '.ck-column-bars { display: flex; align-items: stretch; gap: 12px; height: 232px; position: relative; z-index: 1; }',
      '.ck-column-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; }',
      '.ck-column-val { font-size: 13px; font-weight: 800; color: var(--secondary, #1e3a5f); margin-bottom: 6px; white-space: nowrap; line-height: 1; flex-shrink: 0; }',
      '.ck-column-bar-area { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; min-height: 0; }',
      '.ck-column-bar { width: 78%; max-width: 52px; border-radius: 6px 6px 0 0; min-height: 8px; transition: height 0.4s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }',
      '.ck-column-meta { flex-shrink: 0; padding-top: 10px; text-align: center; width: 100%; }',
      '.ck-column-label { display: block; font-size: 12px; font-weight: 700; color: var(--secondary, #1e3a5f); line-height: 1.3; }',
      '.ck-column-sub { display: block; font-size: 10px; font-weight: 600; color: var(--text-muted, #64748b); margin-top: 2px; }',
      '.ck-column-sub.up { color: #059669; }',
      '.ck-column-sub.down { color: #dc2626; }',
      '.ck-column-footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border, #e2e8f0); display: flex; flex-wrap: wrap; gap: 10px 18px; font-size: 12px; color: var(--text-muted, #64748b); }',
      '.ck-column-footer strong { color: var(--secondary, #1e3a5f); font-weight: 800; }'
    ].join('');
    document.head.appendChild(css);
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function statCards(stats) {
    if (!stats || !stats.length) return '';
    return '<div class="ck-stats">' + stats.map(function (s) {
      var bg = s.bg || 'rgba(100,116,139,0.1)';
      return '<div class="ck-stat">' +
        '<div class="ck-stat-icon" style="background:' + bg + '">' + esc(s.icon || '📊') + '</div>' +
        '<div><div class="ck-stat-label">' + esc(s.label) + '</div>' +
        '<div class="ck-stat-value" style="' + (s.color ? 'color:' + s.color : '') + '">' + esc(s.value) + '</div>' +
        (s.sub ? '<div class="ck-stat-sub">' + esc(s.sub) + '</div>' : '') +
        '</div></div>';
    }).join('') + '</div>';
  }

  function chartCardCls(ch) {
    return 'ck-chart-card' + (ch && ch.wide ? ' ck-wide' : '');
  }

  function barChart(ch) {
    var title = ch.title;
    var items = ch.items;
    var color = ch.color || 'var(--primary, #7c3aed)';
    if (!items || !items.length) return '';
    var max = Math.max.apply(null, items.map(function (i) { return i.value; }).concat([1]));
    var rows = items.map(function (item) {
      var pct = Math.round((item.value / max) * 100);
      var c = item.color || color;
      return '<div class="ck-bar-row">' +
        '<span class="ck-bar-label">' + esc(item.label) + '</span>' +
        '<div class="ck-bar-track"><div class="ck-bar-fill" style="width:' + pct + '%;background:' + c + '"></div></div>' +
        '<span class="ck-bar-val">' + esc(item.display != null ? item.display : item.value) + '</span></div>';
    }).join('');
    return '<div class="' + chartCardCls(ch) + '"><h4>' + esc(title) + '</h4>' + rows + '</div>';
  }

  function donutChart(ch) {
    var title = ch.title;
    var segments = ch.items || [];
    if (!segments.length) return '';
    var total = segments.reduce(function (s, seg) { return s + seg.value; }, 0) || 1;
    var deg = 0;
    var parts = segments.map(function (seg) {
      var span = (seg.value / total) * 360;
      var from = deg;
      deg += span;
      return (seg.color || '#64748b') + ' ' + from + 'deg ' + deg + 'deg';
    }).join(', ');
    var legend = segments.map(function (seg) {
      var pct = Math.round((seg.value / total) * 100);
      var valText = seg.display != null ? seg.display : seg.value + ' (' + pct + '%)';
      return '<div class="ck-legend-item"><span class="ck-legend-dot" style="background:' + (seg.color || '#64748b') + '"></span>' +
        '<span class="ck-legend-label">' + esc(seg.label) + '</span>' +
        '<span class="ck-legend-val">' + esc(valText) + '</span></div>';
    }).join('');
    var wrapCls = 'ck-donut-wrap' + (ch.large ? ' ck-donut-lg' : '');
    var summaryHtml = '';
    if (ch.summary && ch.summary.length) {
      summaryHtml = '<div class="ck-donut-summary">' + ch.summary.map(function (s) {
        return '<div class="ck-donut-summary-item">' +
          '<div class="ck-donut-summary-label">' + esc(s.label) + '</div>' +
          '<div class="ck-donut-summary-value" style="' + (s.color ? 'color:' + s.color : '') + '">' + esc(s.value) + '</div></div>';
      }).join('') + '</div>';
    }
    return '<div class="' + chartCardCls(ch) + '"><h4>' + esc(title) + '</h4>' +
      '<div class="' + wrapCls + '">' +
      '<div class="ck-donut" style="background:conic-gradient(' + parts + ')"><div class="ck-donut-hole">' + total + '</div></div>' +
      '<div class="ck-legend">' + legend + '</div></div>' + summaryHtml + '</div>';
  }

  function formatAxisVal(v, unit, axisOnly) {
    if (unit === 'EGP_K') {
      var n = Math.round(v).toLocaleString('ar-EG');
      return axisOnly ? n : n + ' ألف ج.م';
    }
    if (unit === 'M') return (Math.round(v * 10) / 10) + 'M';
    if (unit === 'count') return String(Math.round(v));
    if (unit === 'K') return Math.round(v).toLocaleString('ar-EG') + ' ألف ج.م';
    return Math.round(v * 10) / 10;
  }

  function columnChart(ch) {
    var items = ch.items || [];
    var color = ch.color || '#7c3aed';
    var unit = ch.unit || '';
    if (!items.length) return '';
    var max = Math.max.apply(null, items.map(function (i) { return i.value; }).concat([1]));
    var ticks = 4;
    var yLabels = [];
    for (var t = ticks; t >= 0; t--) {
      yLabels.push(formatAxisVal(max * t / ticks, unit, true));
    }
    var gridLines = yLabels.map(function () {
      return '<div class="ck-column-gridline"></div>';
    }).join('');
    var cols = items.map(function (item) {
      var h = Math.max(10, Math.round((item.value / max) * 100));
      var subCls = 'ck-column-sub';
      if (item.sub && item.sub.indexOf('↑') !== -1) subCls += ' up';
      else if (item.sub && item.sub.indexOf('↓') !== -1) subCls += ' down';
      return '<div class="ck-column-col">' +
        '<span class="ck-column-val">' + esc(item.display != null ? item.display : formatAxisVal(item.value, unit)) + '</span>' +
        '<div class="ck-column-bar-area">' +
        '<div class="ck-column-bar" style="height:' + h + '%;background:' + (item.color || color) + '" title="' + esc(item.label) + ': ' + esc(item.display || item.value) + '"></div>' +
        '</div>' +
        '<div class="ck-column-meta">' +
        '<span class="ck-column-label">' + esc(item.label) + '</span>' +
        (item.sub ? '<span class="' + subCls + '">' + esc(item.sub) + '</span>' : '') +
        '</div></div>';
    }).join('');
    var total = items.reduce(function (s, i) { return s + i.value; }, 0);
    var avg = total / items.length;
    var peak = items.reduce(function (a, b) { return a.value >= b.value ? a : b; });
    var footer = ch.footer;
    if (!footer) {
      footer = 'إجمالي: <strong>' + formatAxisVal(total, unit) + '</strong>' +
        ' · متوسط: <strong>' + formatAxisVal(avg, unit) + '</strong>' +
        ' · الأعلى: <strong>' + esc(peak.label) + ' ' + esc(peak.display || formatAxisVal(peak.value, unit)) + '</strong>';
    }
    var wideCls = ch.wide ? ' ck-wide' : '';
    return '<div class="ck-chart-card' + wideCls + '"><h4>' + esc(ch.title) + '</h4>' +
      '<div class="ck-column-chart">' +
      '<div class="ck-column-yaxis">' + yLabels.map(function (l) { return '<span>' + esc(l) + '</span>'; }).join('') + '</div>' +
      '<div class="ck-column-area">' +
      '<div class="ck-column-grid">' + gridLines + '</div>' +
      '<div class="ck-column-bars">' + cols + '</div></div></div>' +
      '<div class="ck-column-footer">' + footer + '</div></div>';
  }

  function sparkChart(title, items, color) {
    color = color || '#059669';
    if (!items || !items.length) return '';
    var max = Math.max.apply(null, items.map(function (i) { return i.value; }).concat([1]));
    var cols = items.map(function (item) {
      var h = Math.max(8, Math.round((item.value / max) * 100));
      return '<div class="ck-spark-col">' +
        '<div class="ck-spark-bar-wrap">' +
        '<div class="ck-spark-bar" style="height:' + h + '%;background:' + (item.color || color) + '" title="' + esc(item.label) + ': ' + item.value + '"></div>' +
        '</div>' +
        '<span class="ck-spark-label">' + esc(item.label) + '</span>' +
        '</div>';
    }).join('');
    return '<div class="ck-chart-card"><h4>' + esc(title) + '</h4>' +
      '<div class="ck-spark-wrap">' + cols + '</div></div>';
  }

  function mount(elId, config) {
    injectStyles();
    var el = document.getElementById(elId);
    if (!el || !config) return;
    var html = '<div class="ck-analytics">';
    if (config.stats) html += statCards(config.stats);
    if (config.charts && config.charts.length) {
      html += '<div class="ck-charts">';
      config.charts.forEach(function (ch) {
        if (ch.type === 'bar') html += barChart(ch);
        else if (ch.type === 'donut') html += donutChart(ch);
        else if (ch.type === 'column') html += columnChart(ch);
        else if (ch.type === 'spark') html += sparkChart(ch.title, ch.items, ch.color);
      });
      html += '</div>';
    }
    html += '</div>';
    el.innerHTML = html;
  }

  return { mount: mount, statCards: statCards, barChart: barChart, donutChart: donutChart };
})();
