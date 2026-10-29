/**
 * كل وثيقة تُطبع في ورقة A4 واحدة وتملا عرض الورقة.
 * لو المحتوى أطول من الصفحة: بنصغّر بأكبر نسبة ممكنة مع الحفاظ على عرض الورقة كامل
 * (العرض بيتوسّع بنفس النسبة قبل التصغير، فالسطور تطول والالتفاف يقل) — بدل التصغير
 * الموحّد اللي كان بيسيب هوامش فاضية كبيرة يمين وشمال.
 * الارتفاع المتاح من data-fit-mm على body (افتراضي 268مم = 297 − هوامش @page 12+15 − 2مم أمان)،
 * والعرض من data-print-width-mm (افتراضي 180مم = 210 − 15 − 15).
 */
(function () {
  var MM = 96 / 25.4;

  function measure(sheet, widthMm) {
    sheet.style.width = widthMm + 'mm';
    // ارتفاع المحتوى الحقيقي حتى لو مقصوص بـ max-height / overflow:hidden.
    return Math.max(sheet.scrollHeight, sheet.getBoundingClientRect().height);
  }

  function fitSheet(sheet, limitMm, widthMm) {
    var limitPx = limitMm * MM;

    if (!sheet.hasAttribute('data-fit-orig')) {
      sheet.setAttribute('data-fit-orig', '1');
      sheet.__fitOrig = {
        width: sheet.style.width,
        minHeight: sheet.style.minHeight,
        maxHeight: sheet.style.maxHeight,
        maxWidth: sheet.style.maxWidth,
        hasMinHeight: parseFloat(getComputedStyle(sheet).minHeight) > 0,
      };
    }
    var orig = sheet.__fitOrig;

    sheet.style.zoom = '';
    sheet.style.maxWidth = 'none';
    sheet.style.maxHeight = 'none';
    sheet.style.minHeight = '0';

    var natural = measure(sheet, widthMm);
    if (natural <= limitPx + 0.5) {
      sheet.style.width = orig.width;
      sheet.style.minHeight = orig.minHeight;
      sheet.style.maxHeight = orig.maxHeight;
      sheet.style.maxWidth = orig.maxWidth;
      return;
    }

    // أكبر نسبة s بحيث: ارتفاع المحتوى بعرض (W / s) × s ≤ المتاح.
    var lo = 0.5;
    var hi = 1;
    for (var i = 0; i < 12; i++) {
      var mid = (lo + hi) / 2;
      if (measure(sheet, widthMm / mid) * mid <= limitPx) lo = mid;
      else hi = mid;
    }
    var scale = Math.floor(lo * 1000) / 1000;

    sheet.style.width = (widthMm / scale) + 'mm';
    // الورقة اللي ليها ارتفاع صفحة كامل (عرض السعر/الفاتورة) تفضل مالية الصفحة بعد التصغير.
    sheet.style.minHeight = orig.hasMinHeight ? (limitMm / scale) + 'mm' : '0';
    sheet.style.zoom = String(scale);
  }

  function fitSheets() {
    var body = document.body;
    if (!body) return;
    var limitMm = parseFloat(body.getAttribute('data-fit-mm')) || 268;
    var widthMm = parseFloat(body.getAttribute('data-print-width-mm')) || 180;

    document.querySelectorAll('.sheet').forEach(function (sheet) {
      fitSheet(sheet, limitMm, widthMm);
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
