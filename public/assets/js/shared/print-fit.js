/**
 * كل وثيقة تُطبع في ورقة A4 واحدة: لو المحتوى أطول من الصفحة يتصغّر بالنسبة المطلوبة بالظبط
 * (بدل ما يقع في ورقتين أو يتقص آخره). الارتفاع المتاح من data-fit-mm على body
 * (افتراضي 268مم = 297 − هوامش @page 12+15 − 2مم أمان).
 */
(function () {
  var MM = 96 / 25.4;

  function fitSheets() {
    var body = document.body;
    if (!body) return;
    var limitMm = parseFloat(body.getAttribute('data-fit-mm')) || 268;
    // عرض الطباعة الفعلي: A4 (210مم) − هوامش @page الجانبية (15+15) = 180مم.
    var widthMm = parseFloat(body.getAttribute('data-print-width-mm')) || 180;
    var limitPx = limitMm * MM;

    document.querySelectorAll('.sheet').forEach(function (sheet) {
      sheet.style.zoom = '';
      sheet.style.maxHeight = '';

      // القياس بنفس عرض الورقة المطبوعة — عرض الشاشة بيغيّر التفاف السطور وبالتالي الارتفاع.
      var prevWidth = sheet.style.width;
      var prevMaxWidth = sheet.style.maxWidth;
      sheet.style.width = widthMm + 'mm';
      sheet.style.maxWidth = 'none';

      // ارتفاع المحتوى الحقيقي حتى لو مقصوص بـ max-height / overflow:hidden.
      var natural = Math.max(sheet.scrollHeight, sheet.getBoundingClientRect().height);

      sheet.style.width = prevWidth;
      sheet.style.maxWidth = prevMaxWidth;

      if (natural <= limitPx + 0.5) return;

      var scale = Math.floor((limitPx / natural) * 1000) / 1000;
      sheet.style.maxHeight = 'none';
      sheet.style.zoom = String(scale);
    });
  }

  window.__fitSheetsToA4 = fitSheets;
  window.addEventListener('beforeprint', fitSheets);
  if (window.matchMedia) {
    var mq = window.matchMedia('print');
    if (mq.addEventListener) mq.addEventListener('change', function (e) { if (e.matches) fitSheets(); });
    else if (mq.addListener) mq.addListener(function (e) { if (e.matches) fitSheets(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fitSheets);
  else fitSheets();
  window.addEventListener('load', fitSheets);
})();
