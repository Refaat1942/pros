/**
 * ExportKit — تصدير Excel (CSV) و PDF + مساعدات الفلترة
 * مشترك بين جميع لوحات التحكم
 */
var ExportKit = (function () {

  function escapeCsv(val) {
    return '"' + String(val == null ? '' : val).replace(/"/g, '""') + '"';
  }

  function download(content, filename, mime) {
    var blob = new Blob([content], { type: mime });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  }

  function notify(msg) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg);
      return;
    }
    var t = document.getElementById('toast');
    if (t) {
      t.textContent = '✅ ' + msg;
      t.classList.add('show');
      setTimeout(function () { t.classList.remove('show'); }, 5000);
    }
  }

  function toExcel(filename, headers, rows) {
    if (!rows || !rows.length) {
      alert('لا توجد بيانات للتصدير');
      return;
    }
    var BOM = '\uFEFF';
    var lines = [headers.map(escapeCsv).join(',')];
    rows.forEach(function (row) {
      lines.push(row.map(escapeCsv).join(','));
    });
    download(BOM + lines.join('\n'), filename + '.csv', 'text/csv;charset=utf-8;');
    notify('تم تصدير Excel بنجاح — ' + rows.length + ' سجل');
  }

  function toPDF(title, headers, rows, subtitle) {
    if (!rows || !rows.length) {
      alert('لا توجد بيانات للتصدير');
      return;
    }
    var date = new Date().toLocaleString('ar-EG');
    var html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">';
    html += '<title>' + title + '</title>';
    html += '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">';
    html += '<style>';
    html += '*{box-sizing:border-box;margin:0;padding:0}';
    html += 'body{font-family:Tajawal,sans-serif;padding:32px;color:#1e293b}';
    html += 'h1{font-size:20px;margin-bottom:4px;color:#1e3a5f}';
    html += '.meta{font-size:12px;color:#64748b;margin-bottom:20px}';
    html += 'table{width:100%;border-collapse:collapse;font-size:12px}';
    html += 'th{background:#1e3a5f;color:#fff;padding:10px 12px;text-align:right;font-weight:600}';
    html += 'td{padding:9px 12px;border-bottom:1px solid #e2e8f0;text-align:right}';
    html += 'tr:nth-child(even){background:#f8fafc}';
    html += '@media print{body{padding:16px}@page{margin:1.5cm}}';
    html += '</style></head><body>';
    html += '<h1>' + title + '</h1>';
    html += '<p class="meta">' + (subtitle || 'مركز إنتاج الأطراف الصناعية') + ' — تاريخ التصدير: ' + date + '</p>';
    html += '<table><thead><tr>';
    headers.forEach(function (h) { html += '<th>' + h + '</th>'; });
    html += '</tr></thead><tbody>';
    rows.forEach(function (row) {
      html += '<tr>';
      row.forEach(function (c) { html += '<td>' + c + '</td>'; });
      html += '</tr>';
    });
    html += '</tbody></table></body></html>';

    var w = window.open('', '_blank');
    if (!w) {
      alert('يرجى السماح بالنوافذ المنبثقة لتصدير PDF');
      return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
    w.onload = function () {
      setTimeout(function () { w.print(); }, 500);
    };
    notify('جاري فتح معاينة PDF — ' + rows.length + ' سجل');
  }

  /** فلترة عامة: بحث نصي + فلتر حقل */
  function filterItems(items, opts) {
    opts = opts || {};
    var search = (opts.search || '').trim();
    var searchKeys = opts.searchKeys || [];
    var filterField = opts.filterField;
    var filterValue = opts.filterValue;

    return items.filter(function (item) {
      var matchSearch = !search || searchKeys.some(function (key) {
        return String(item[key] || '').indexOf(search) !== -1;
      });
      var matchFilter = !filterField || filterValue === 'all' || !filterValue ||
        String(item[filterField]) === String(filterValue);
      return matchSearch && matchFilter;
    });
  }

  return {
    toExcel: toExcel,
    toPDF: toPDF,
    filterItems: filterItems
  };
})();
