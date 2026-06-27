/**
 * ExportKit — تصدير Excel (CSV) و PDF + مساعدات الفلترة
 * مشترك بين جميع لوحات التحكم
 */
var ExportKit = (function () {

  function escapeCsv(val) {
    return '"' + String(val == null ? '' : val).replace(/"/g, '""') + '"';
  }

  /** يمنع Excel من تحويل التاريخ لرقم serial — صيغة dd/mm/yyyy كنص */
  function formatDateForExport(val) {
    if (val == null || val === '' || val === '—') return '—';
    var s = String(val).trim();
    var isoDateTime = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (isoDateTime) {
      var formatted = isoDateTime[3] + '/' + isoDateTime[2] + '/' + isoDateTime[1];
      if (isoDateTime[4] != null) {
        formatted += ' ' + isoDateTime[4] + ':' + isoDateTime[5];
        if (isoDateTime[6] != null) formatted += ':' + isoDateTime[6];
      }
      return '\t' + formatted;
    }
    if (/^\d{1,2}\/\d{1,2}\/\d{4}(\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(s)) {
      return '\t' + s;
    }
    return '\t' + s;
  }

  function sanitizeCellForExcel(text) {
    var value = String(text == null ? '' : text).replace(/\s+/g, ' ').trim();
    if (!value || value === '—') return value || '';
    if (/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/.test(value)) {
      return formatDateForExport(value);
    }
    return value;
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

  function cellText(cell) {
    if (!cell) return '';
    var clone = cell.cloneNode(true);
    clone.querySelectorAll('button, input, .bulk-checkbox, .rank-drag-handle, .contract-actions, .table-actions').forEach(function (el) {
      el.remove();
    });
    return String(clone.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function shouldSkipHeader(th) {
    if (!th) return true;
    if (th.classList.contains('bulk-select-col') || th.classList.contains('rank-drag-col') || th.classList.contains('col-actions')) {
      return true;
    }
    var label = (th.getAttribute('aria-label') || '').trim();
    if (label.indexOf('سحب') !== -1 || label.indexOf('تحديد') !== -1) return true;
    return false;
  }

  /** تصدير جدول HTML — كل الصفوف (بما فيها صفحات الترقيم المخفية). */
  function fromVisibleTable(selector, options) {
    options = options || {};
    var table = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (!table) {
      alert('الجدول غير موجود');
      return;
    }

    var thead = table.tHead || table.querySelector('thead');
    var tbody = table.tBodies[0] || table.querySelector('tbody');
    if (!thead || !tbody) {
      alert('لا توجد بيانات للتصدير');
      return;
    }

    var headerCells = thead.querySelectorAll('th');
    var headers = [];
    var includeIndexes = [];
    headerCells.forEach(function (th, index) {
      if (shouldSkipHeader(th)) return;
      headers.push(String(th.textContent || '').replace(/\s+/g, ' ').trim());
      includeIndexes.push(index);
    });

    if (!headers.length) {
      alert('لا توجد أعمدة للتصدير');
      return;
    }

    var rows = [];
    tbody.querySelectorAll('tr').forEach(function (tr) {
      if (tr.querySelector('td[colspan]')) return;
      var cells = tr.querySelectorAll('td');
      if (!cells.length) return;
      var row = includeIndexes.map(function (index) {
        return sanitizeCellForExcel(cellText(cells[index]));
      });
      if (row.some(function (value) { return value !== ''; })) {
        rows.push(row);
      }
    });

    toExcel(options.filename || 'export', headers, rows);
  }

  function fromAuditList(selector, options) {
    options = options || {};
    var container = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (!container) {
      alert('لا توجد بيانات للتصدير');
      return;
    }

    var headers = ['الوقت', 'المستخدم', 'الوصف', 'العملية'];
    var rows = [];
    container.querySelectorAll('.audit-item').forEach(function (item) {
      if (item.style.display === 'none') return;
      var descEl = item.querySelector('.audit-desc');
      var user = item.querySelector('.audit-desc strong');
      var desc = descEl ? descEl.textContent.replace((user && user.textContent) || '', '').replace(/—/g, ' ').replace(/\s+/g, ' ').trim() : '';
      rows.push([
        sanitizeCellForExcel(cellText(item.querySelector('.audit-time'))),
        user ? user.textContent.trim() : '',
        desc,
        cellText(item.querySelector('.audit-tag')),
      ]);
    });

    toExcel(options.filename || 'audit-log', headers, rows);
  }

  function fromPermissions(filename) {
    var roleName = document.getElementById('permRoleBannerName');
    var role = roleName ? roleName.textContent.trim() : 'الدور';
    var headers = ['الدور', 'اللوحة', 'الصلاحية', 'مفعّل'];
    var rows = [];

    document.querySelectorAll('.perm-visible-cb').forEach(function (cb) {
      var card = cb.closest('.perm-card');
      var dashboard = card ? card.querySelector('.perm-card-title') : null;
      var label = cb.closest('.perm-toggle');
      var permLabel = label ? label.querySelector('.perm-toggle-text strong') : null;
      rows.push([
        role,
        dashboard ? dashboard.textContent.trim() : '—',
        permLabel ? permLabel.textContent.trim() : (cb.getAttribute('data-slug') || '—'),
        cb.checked ? 'نعم' : 'لا',
      ]);
    });

    toExcel(filename || 'permissions', headers, rows);
  }

  return {
    toExcel: toExcel,
    toPDF: toPDF,
    filterItems: filterItems,
    formatDateForExport: formatDateForExport,
    fromVisibleTable: fromVisibleTable,
    fromAuditList: fromAuditList,
    fromPermissions: fromPermissions,
  };
})();
