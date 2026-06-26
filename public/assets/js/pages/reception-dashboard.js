    var now = new Date();
    var TODAY_DATE = '';
    var MIN_SELECTABLE_DATE = '';

    var calendarView = {
      year: now.getFullYear(),
      month: now.getMonth() + 1,
      selectedDate: ''
    };

    var appointmentsLoading = false;

    var AR_MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    var VISIT_TYPES = {
      exam: { label: 'كشف أولي', dotClass: 'cal-dot-exam', tagClass: 'visit-exam' },
      followup: { label: 'متابعة', dotClass: 'cal-dot-followup', tagClass: 'visit-followup' },
      fitting: { label: 'تركيب', dotClass: 'cal-dot-fitting', tagClass: 'visit-fitting' },
      delivery: { label: 'استلام', dotClass: 'cal-dot-delivery', tagClass: 'visit-delivery' },
      review: { label: 'مراجعة', dotClass: 'cal-dot-review', tagClass: 'visit-review' }
    };

    var appointments = [];
    var quotations = [];
    var quoteSearchTerm = '';
    var patientsRegistry = [];

    function formatQuoteAmount(n) {
      return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function syncPricingQueueToQuotes() {}

    /**
     * تحميل عروض الأسعار من الخادم — يُستدعى عند تحميل صفحة "عروض الأسعار".
     * يُعبئ مصفوفة quotations ثم يُعيد رسم الجدول.
     */
    function fetchServerQuotes() {
      if (document.body.dataset.activePage !== 'quote') return;

      fetch('/reception/quote/list', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (payload) {
          if (!payload || !Array.isArray(payload.data)) return;

          quotations = payload.data.map(function (q) {
            return {
              id:          q.quote_no,
              _dbId:       q.id,
              printUrl:    q.print_url || null,
              patient:     q.patient_name,
              company:     q.company_name,
              orderRef:    q.order_ref,
              date:        displayDateFromIso(q.quote_date),
              total:       parseFloat(q.total) || 0,
              status:      q.status,
              statusLabel: q.status_label,
              items:       (q.items || []).map(function (i) {
                return { name: i.name, qty: i.qty, amount: parseFloat(i.amount) || 0 };
              })
            };
          });

          renderQuoteTable();
          renderQuoteAnalytics();

          var searchEl = document.getElementById('quoteSearch');
          if (searchEl) searchEl.focus();
        })
        .catch(function (err) { console.error('fetchServerQuotes failed', err); });
    }

    /**
     * إصدار عرض السعر رسمياً للجهة التعاقدية عبر API.
     */
    function issueQuoteToEntity(dbId) {
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

      fetch('/reception/quote/' + dbId + '/issue', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
      })
        .then(function (r) {
          return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function (res) {
          var q = quotations.find(function (q) { return q._dbId === dbId; });
          if (q && res.quote) {
            q.status      = res.quote.status;
            q.statusLabel = res.quote.status_label;
          }
          renderQuoteTable();
          renderQuoteAnalytics();
          showToast('تم إصدار عرض السعر للجهة بنجاح');
        })
        .catch(function (err) {
          showToast((err && err.message) ? err.message : 'تعذّر إصدار العرض للجهة', true);
        });
    }
    window.issueQuoteToEntity = issueQuoteToEntity;

    /**
     * تحديث بطاقات الإحصاء في صفحة عروض الأسعار.
     */
    function renderQuoteAnalytics() {
      var container = document.getElementById('analytics-quote');
      if (!container) return;

      var total    = quotations.length;
      var pending  = quotations.filter(function (q) { return q.status === 'pending'; }).length;
      var issued   = quotations.filter(function (q) { return q.status === 'issued'; }).length;
      var approved = quotations.filter(function (q) { return q.status === 'approved'; }).length;

      function setVal(idx, val) {
        var els = container.querySelectorAll('.ck-stat-value');
        if (els[idx]) els[idx].textContent = String(val);
      }

      setVal(0, total);
      setVal(1, approved);
      setVal(2, pending + issued);
    }

    function markQuoteAsIssued(quote) {
      if (!quote) return;
      CasesWorkflow.onQuoteIssued(quote.id, {
        orderRef: quote.orderRef,
        patient: quote.patient,
        company: quote.company,
        date: quote.date,
        total: quote.total
      });
    }

    function normalizeScanCode(raw) {
      return String(raw || '').trim().toUpperCase();
    }

    function findQuoteByScanCode(code) {
      var normalized = normalizeScanCode(code);
      if (!normalized) return null;
      return quotations.find(function (q) {
        return normalizeScanCode(q.id) === normalized;
      }) || null;
    }

    /**
     * معالجة مسح الباركود/QR — يُستدعى عند Enter من جهاز الماسح.
     */
    function handleQuoteBarcodeScan(raw) {
      var code = normalizeScanCode(raw);
      if (!code) return;

      quoteSearchTerm = code;
      renderQuoteTable();

      var quote = findQuoteByScanCode(code);
      if (!quote) {
        showToast('لم يُعثَر على عرض سعر مطابق: ' + code);
        return;
      }

      if (quote.status === 'issued') {
        openOcrApprovalModal(quote);
        return;
      }

      if (quote.status === 'pending') {
        showToast('يجب إصدار العرض للجهة أولاً — ' + quote.id);
      }

      openQuoteModal(quote.id);
    }

    function getFilteredQuotations() {
      return quotations.filter(function(q) {
        if (!quoteSearchTerm) return true;
        var term = quoteSearchTerm.toUpperCase();
        return q.id.toUpperCase().indexOf(term) !== -1 ||
          q.patient.indexOf(quoteSearchTerm) !== -1 ||
          q.company.indexOf(quoteSearchTerm) !== -1;
      });
    }

    function getQuoteDocumentHtml(quote) {
      if (!quote) return '';
      var rows = quote.items.map(function(item) {
        return '<tr><td>' + item.name + '</td><td>' + item.qty + '</td><td>' + formatQuoteAmount(item.amount) + '</td></tr>';
      }).join('');

      return '<div class="quote-header">' +
          '<h2>مركز إنتاج وتصنيع الأطراف الصناعية</h2>' +
          '<p>وثيقة عرض سعر رسمية — غير قابلة للتعديل</p>' +
        '</div>' +
        '<div class="quote-body">' +
          '<div class="quote-meta">' +
            '<div class="item"><div class="lbl">رقم العرض</div><div class="val">' + quote.id + '</div></div>' +
            '<div class="item"><div class="lbl">التاريخ</div><div class="val">' + quote.date + '</div></div>' +
            '<div class="item"><div class="lbl">اسم المريض</div><div class="val">' + quote.patient + '</div></div>' +
            '<div class="item"><div class="lbl">جهة التعاقد</div><div class="val">' + quote.company + '</div></div>' +
          '</div>' +
          '<div class="quote-items"><table><thead><tr><th>البند</th><th>الكمية</th><th>القيمة (ج.م)</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
          '<div class="quote-total">' +
            '<div class="label">إجمالي عرض السعر المعتمد</div>' +
            '<div class="amount">' + formatQuoteAmount(quote.total) + ' ج.م</div>' +
            '<div class="locked">🔒 وثيقة مقفلة — Highest Batch Cost Logic</div>' +
          '</div>' +
          '<div class="qr-section">' +
            '<div class="qr-code"><canvas id="qrCanvas" width="100" height="100"></canvas></div>' +
            '<div class="qr-info">' +
              '<strong>رمز QR فريد للعرض — ' + quote.id + '</strong>' +
              'امسح هذا الرمز عند عودة المريض بخطاب الموافقة لاسترجاع الطلب الأصلي والسعر المثبت فوراً دون إعادة إدخال البيانات.' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    var qrPattern = [
      [1,1,1,1,1,1,1,0,1,0,1,1,1,0,1,1,1,1,1,1],
      [1,0,0,0,0,0,1,0,0,1,0,0,1,0,1,0,0,0,0,0,1],
      [1,0,1,1,1,0,1,0,1,1,0,1,0,0,1,0,1,1,1,0,1],
      [1,0,1,1,1,0,1,0,0,1,1,0,1,0,1,0,1,1,1,0,1],
      [1,0,1,1,1,0,1,0,1,0,0,1,1,0,1,0,1,1,1,0,1],
      [1,0,0,0,0,0,1,0,0,1,0,0,0,0,1,0,0,0,0,0,1],
      [1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,1,1,1,1,1,1],
      [0,0,0,0,0,0,0,0,1,1,0,1,0,0,0,0,0,0,0,0,0],
      [1,0,1,0,1,1,1,1,0,1,1,0,1,1,0,1,0,1,1,0,1],
      [0,1,0,1,0,0,0,1,1,0,0,1,0,1,1,0,1,0,0,1,0],
      [1,1,0,1,1,0,1,0,0,1,1,0,1,0,0,1,1,0,1,1,1],
      [0,0,1,0,1,1,0,1,1,0,1,0,0,1,1,0,0,1,0,0,0],
      [1,0,1,1,0,0,1,0,1,1,0,1,1,0,1,0,1,1,0,1,1],
      [0,0,0,0,0,0,0,0,0,1,1,0,1,0,0,1,0,0,1,0,0],
      [1,1,1,1,1,1,1,0,1,0,0,1,0,1,1,0,1,1,0,1,0],
      [1,0,0,0,0,0,1,0,0,1,1,0,1,0,0,1,0,0,1,1,1],
      [1,0,1,1,1,0,1,0,1,1,0,1,0,1,1,0,1,0,0,0,1],
      [1,0,1,1,1,0,1,0,0,0,1,1,0,1,0,1,0,1,1,0,0],
      [1,0,0,0,0,0,1,0,1,0,0,1,1,0,1,0,1,0,0,1,1],
      [1,1,1,1,1,1,1,0,0,1,1,0,1,1,0,1,1,1,0,0,1]
    ];

    function hashQuoteId(id) {
      var h = 0;
      for (var i = 0; i < id.length; i++) {
        h = ((h << 5) - h) + id.charCodeAt(i);
        h |= 0;
      }
      return Math.abs(h);
    }

    function drawQR(quoteId) {
      var canvas = document.getElementById('qrCanvas');
      if (!canvas) return;
      var ctx = canvas.getContext('2d');
      var size = 100;
      var moduleSize = 5;
      var seed = hashQuoteId(quoteId || 'QT-DEFAULT');

      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = '#1e3a5f';

      for (var y = 0; y < qrPattern.length; y++) {
        for (var x = 0; x < qrPattern[y].length; x++) {
          var bit = qrPattern[y][x];
          if ((x + y + seed) % 7 === 0) bit = bit ? 0 : 1;
          if (bit) {
            ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
          }
        }
      }
    }

    function openQRScan(presetQuoteId) {
      var modal = document.getElementById('qrModal');
      var status = document.getElementById('scanStatus');
      var hint = document.getElementById('scanQuoteHint');
      var quote = quotations.find(function(q) { return q.id === presetQuoteId; }) ||
        quotations.find(function(q) { return q.status === 'pending'; }) ||
        quotations[0];
      if (!quote) return;

      hint.textContent = quote.id + ' — ' + quote.patient;
      modal.classList.add('visible');
      status.textContent = 'جاري المسح...';

      setTimeout(function() {
        status.textContent = '✅ تم التعرف على الطلب — ' + quote.id;
      }, 1500);

      setTimeout(function() {
        modal.classList.remove('visible');
        switchTab('quote');
        openQuoteModal(quote.id);
        CasesWorkflow.onApprovalConfirmed({
          quoteId: quote.id,
          orderRef: quote.orderRef,
          patient: quote.patient,
          company: quote.company,
          approvalDate: TODAY_DATE,
          totalCost: quote.total,
          quoteItems: quote.items,
          recommendations: typeof BomInventory !== 'undefined'
            ? BomInventory.parseQuoteItems(quote.items)
            : []
        });
        showToast('تم استرجاع عرض السعر وتأكيد الموافقة — ' + quote.patient);
      }, 3000);
    }

    function closeQRScanModal() {
      document.getElementById('qrModal').classList.remove('visible');
    }

    function quotePrintUrl(quote) {
      if (!quote) return null;
      if (quote.printUrl) return quote.printUrl;
      if (quote._dbId) return '/reception/quote/' + quote._dbId + '/print?embed=1';
      return null;
    }

    function printQuoteModal() {
      var iframe = document.querySelector('#quoteModalBody iframe.quote-print-frame');
      if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        return;
      }
      showToast('تعذّر فتح معاينة الطباعة', true);
    }
    window.printQuoteModal = printQuoteModal;

    function openQuoteModal(id) {
      var quote = quotations.find(function(q) { return q.id === id; });
      if (!quote) return;

      var printUrl = quotePrintUrl(quote);
      if (!printUrl) {
        showToast('تعذّر تحميل نموذج عرض السعر', true);
        return;
      }

      document.getElementById('quoteModalTitle').textContent = quote.id + ' — ' + quote.patient;

      var body = document.getElementById('quoteModalBody');
      body.innerHTML = '';
      var iframe = document.createElement('iframe');
      iframe.className = 'quote-print-frame';
      iframe.setAttribute('title', 'عرض سعر ' + quote.id);
      iframe.src = printUrl;
      body.appendChild(iframe);

      document.getElementById('quoteModal').classList.add('visible');
      if (quote.status === 'approved') {
        markQuoteAsIssued(quote);
        quote.status = 'issued';
        quote.statusLabel = 'صُدر — بانتظار رجوع العميل';
        renderQuoteTable();
      }
    }

    function closeQuoteModal() {
      document.getElementById('quoteModal').classList.remove('visible');
    }

    function renderQuoteTable() {
      var filtered = getFilteredQuotations();
      var tbody = document.getElementById('quotesTable');
      if (!tbody) return;
      tbody.innerHTML = filtered.map(function (q) {
        var viewBtn = '<button type="button" class="btn btn-secondary" style="padding:6px 14px;font-size:12px;" onclick="openQuoteModal(\'' + q.id + '\')">عرض</button>';
        var issueBtn = q.status === 'pending' && q._dbId
          ? ' <button type="button" class="btn btn-primary" style="padding:6px 14px;font-size:12px;margin-right:4px;" onclick="issueQuoteToEntity(' + q._dbId + ')">إصدار للجهة</button>'
          : '';
        var ocrBtn = q.status === 'issued'
          ? ' <button type="button" class="btn" style="padding:6px 14px;font-size:12px;margin-right:4px;background:#059669;color:#fff;border:none;border-radius:6px;cursor:pointer;" onclick="openOcrApprovalModal(quotations.find(function(x){return x.id===\'' + q.id + '\';}))">📄 رفع خطاب الموافقة</button>'
          : '';
        return '<tr>' +
          '<td><strong style="color:var(--primary);">' + q.id + '</strong></td>' +
          '<td><strong>' + q.patient + '</strong></td>' +
          '<td>' + q.company + '</td>' +
          '<td>' + q.date + '</td>' +
          '<td><strong>' + formatQuoteAmount(q.total) + ' ج.م</strong></td>' +
          '<td><span class="quote-status-tag ' + q.status + '">' + q.statusLabel + '</span></td>' +
          '<td>' + viewBtn + issueBtn + ocrBtn + '</td>' +
          '</tr>';
      }).join('') || '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد عروض مطابقة</td></tr>';

      document.getElementById('quoteListCount').textContent = quotations.length;
      document.getElementById('quoteFilterCount').textContent = filtered.length + ' عروض';
      if (window.TablePagination) TablePagination.refreshById('quotesTable');
    }

    function pad2(n) { return String(n).padStart(2, '0'); }

    function formatDateKey(day, month, year) {
      return pad2(day) + '/' + pad2(month) + '/' + year;
    }

    function parseDisplayDate(dateKey) {
      var parts = (dateKey || '').split('/');
      if (parts.length !== 3) return null;
      return new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
    }

    function isFutureDate(dateKey) {
      var d = parseDisplayDate(dateKey);
      var today = parseDisplayDate(TODAY_DATE);
      if (!d || !today) return false;
      d.setHours(0, 0, 0, 0);
      today.setHours(0, 0, 0, 0);
      return d > today;
    }

    function isBeforeMinDate(dateKey) {
      var d = parseDisplayDate(dateKey);
      var min = parseDisplayDate(MIN_SELECTABLE_DATE);
      if (!d || !min) return false;
      d.setHours(0, 0, 0, 0);
      min.setHours(0, 0, 0, 0);
      return d < min;
    }

    function isDateSelectable(dateKey) {
      return !isFutureDate(dateKey) && !isBeforeMinDate(dateKey);
    }

    function clampCalendarMonth() {
      var todayYear = now.getFullYear();
      var todayMonth = now.getMonth() + 1;
      var minYear = now.getFullYear() - 1;
      var minMonth = now.getMonth() + 1;

      if (calendarView.year > todayYear || (calendarView.year === todayYear && calendarView.month > todayMonth)) {
        calendarView.year = todayYear;
        calendarView.month = todayMonth;
      }

      if (calendarView.year < minYear || (calendarView.year === minYear && calendarView.month < minMonth)) {
        calendarView.year = minYear;
        calendarView.month = minMonth;
      }
    }

    function syncSelectionToVisibleMonth() {
      var parts = calendarView.selectedDate.split('/');
      var selMonth = parseInt(parts[1], 10);
      var selYear = parseInt(parts[2], 10);
      if (selMonth === calendarView.month && selYear === calendarView.year && isDateSelectable(calendarView.selectedDate)) {
        return;
      }

      var lastDay = new Date(calendarView.year, calendarView.month, 0).getDate();
      var startDay = lastDay;
      if (calendarView.year === now.getFullYear() && calendarView.month === now.getMonth() + 1) {
        startDay = now.getDate();
      }

      for (var d = startDay; d >= 1; d--) {
        var candidate = formatDateKey(d, calendarView.month, calendarView.year);
        if (isDateSelectable(candidate)) {
          calendarView.selectedDate = candidate;
          return;
        }
      }
    }

    function updateCalendarNavButtons() {
      var prev = document.getElementById('calPrev');
      var next = document.getElementById('calNext');
      var atCurrent = calendarView.year === now.getFullYear() && calendarView.month === now.getMonth() + 1;
      var atMin = calendarView.year === (now.getFullYear() - 1) && calendarView.month === (now.getMonth() + 1);
      if (next) {
        next.disabled = atCurrent;
        next.classList.toggle('cal-nav-disabled', atCurrent);
      }
      if (prev) {
        prev.disabled = atMin;
        prev.classList.toggle('cal-nav-disabled', atMin);
      }
    }

    function displayDateFromIso(iso) {
      if (!iso) return '';
      var raw = String(iso).split('T')[0];
      var parts = raw.split('-');
      if (parts.length !== 3) return raw;
      return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function isoDateFromDisplay(display) {
      if (!display) return '';
      var parts = display.split('/');
      if (parts.length !== 3) return display;
      return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function mapAppointmentFromApi(row) {
      var patient = row.patient || {};
      var visitTypeRel = row.visit_type_record || null;
      var visitTypeName = visitTypeRel && visitTypeRel.name ? visitTypeRel.name : null;
      var isMilitary = (row.patient_type || patient.patient_type) === 'military';
      var affiliation = isMilitary
        ? (patient.rank || row.company_name || '—')
        : (row.company_name || '—');
      return {
        id: row.id,
        date: displayDateFromIso(row.appointment_date),
        time: row.appointment_time ? String(row.appointment_time).substring(0, 5) : '—',
        name: row.patient_name || patient.name || '—',
        phone: row.phone || '—',
        company: affiliation,
        patient_type: row.patient_type || patient.patient_type || 'civilian',
        visitType: row.visit_type_id ? String(row.visit_type_id) : 'exam',
        visitTypeLabel: visitTypeName || getVisitMeta('exam').label,
        status: row.status || 'waiting',
        statusLabel: row.status_label || row.status || 'waiting',
        transferredToClinic: !!row.transferred_to_clinic,
        registeredAt: row.registered_at_formatted || '—',
        waitLabel: row.wait_label || '—'
      };
    }

    function fetchAppointmentsForSelectedDate() {
      if (!calendarView.selectedDate || appointmentsLoading) return Promise.resolve();
      appointmentsLoading = true;
      var iso = isoDateFromDisplay(calendarView.selectedDate);

      return fetch('/reception/appointments/list?date=' + encodeURIComponent(iso), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function(res) {
          if (!res.ok) throw new Error('appointments list failed');
          return res.json();
        })
        .then(function(payload) {
          appointments = (payload.data || []).map(mapAppointmentFromApi);
          renderAppointments();
          renderCalendar();
          renderReceptionAnalytics();
        })
        .catch(function(err) {
          console.error('fetchAppointmentsForSelectedDate', err);
        })
        .finally(function() {
          appointmentsLoading = false;
        });
    }

    function getVisitMeta(visitType) {
      return VISIT_TYPES[visitType] || VISIT_TYPES.exam;
    }

    function getAppointmentsForDate(dateStr) {
      return appointments.filter(function(a) { return a.date === dateStr; });
    }

    function getSelectedDayAppointments() {
      return getAppointmentsForDate(calendarView.selectedDate);
    }

    function selectCalendarDate(dateStr) {
      if (!isDateSelectable(dateStr)) return;
      calendarView.selectedDate = dateStr;
      var parts = dateStr.split('/');
      calendarView.month = parseInt(parts[1], 10);
      calendarView.year = parseInt(parts[2], 10);
      renderCalendar();
      fetchAppointmentsForSelectedDate();
    }

    function renderCalendar() {
      var grid = document.getElementById('calendarGrid');
      var label = document.getElementById('calMonthLabel');
      if (!grid || !label) return;

      var year = calendarView.year;
      var month = calendarView.month;
      label.textContent = AR_MONTHS[month - 1] + ' ' + year;

      var firstDay = new Date(year, month - 1, 1).getDay();
      var daysInMonth = new Date(year, month, 0).getDate();
      var daysInPrev = new Date(year, month - 1, 0).getDate();
      var html = '';

      for (var i = 0; i < firstDay; i++) {
        var pd = daysInPrev - firstDay + i + 1;
        var pm = month === 1 ? 12 : month - 1;
        var py = month === 1 ? year - 1 : year;
        html += buildCalDayCell(pd, formatDateKey(pd, pm, py), true);
      }

      for (var d = 1; d <= daysInMonth; d++) {
        html += buildCalDayCell(d, formatDateKey(d, month, year), false);
      }

      var trailing = (7 - ((firstDay + daysInMonth) % 7)) % 7;
      var nm = month === 12 ? 1 : month + 1;
      var ny = month === 12 ? year + 1 : year;
      for (var t = 1; t <= trailing; t++) {
        html += buildCalDayCell(t, formatDateKey(t, nm, ny), true);
      }

      grid.innerHTML = html;
      grid.querySelectorAll('.cal-day[data-date]').forEach(function(btn) {
        btn.addEventListener('click', function() {
          if (btn.disabled || btn.classList.contains('disabled')) return;
          selectCalendarDate(btn.getAttribute('data-date'));
        });
      });
      updateCalendarNavButtons();
    }

    function buildCalDayCell(dayNum, dateKey, otherMonth) {
      var selectable = isDateSelectable(dateKey);
      var cls = 'cal-day' + (otherMonth ? ' other-month' : '');
      if (!selectable) cls += ' disabled';
      if (dateKey === TODAY_DATE) cls += ' today';
      if (dateKey === calendarView.selectedDate && selectable) cls += ' selected';

      var inner = '<span class="cal-day-num">' + dayNum + '</span>';
      if (!selectable) {
        return '<span class="' + cls + '" aria-disabled="true">' + inner + '</span>';
      }

      return '<button type="button" class="' + cls + '" data-date="' + dateKey + '">' + inner + '</button>';
    }

    function getFilteredAppointments() {
      var search = (document.getElementById('apptSearch') || {}).value || '';
      var status = (document.getElementById('apptStatusFilter') || {}).value || 'all';
      search = search.trim();
      return appointments.filter(function(a) {
        var ms = !search || a.name.indexOf(search) !== -1 || a.phone.indexOf(search) !== -1;
        var mst = status === 'all' || a.status === status;
        var md = a.date === calendarView.selectedDate;
        return ms && mst && md;
      });
    }

    function mapPatientFromApi(row) {
      var statusLabels = {
        active: 'نشط',
        inactive: 'غير نشط',
        quoted: 'عرض سعر',
        done: 'مكتمل'
      };
      var company = row.company_name ||
        (row.contract_company && row.contract_company.name) ||
        '—';
      if (row.patient_type === 'military') {
        company = row.rank || company;
      }
      return {
        id: row.id,
        name: row.name || '—',
        phone: row.phone || '—',
        company: company,
        rank: row.rank || '',
        registered: displayDateFromIso(row.registered_at) || '—',
        lastVisit: displayDateFromIso(row.last_visit_at) || '—',
        status: row.status || 'active',
        statusLabel: statusLabels[row.status] || row.status || 'نشط',
        patient_code: row.patient_code,
        patient_type: row.patient_type
      };
    }

    function fetchPatientsFromServer(callback) {
      return fetch('/reception/patients/list', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (res) {
          if (!res.ok) throw new Error('patients list failed');
          return res.json();
        })
        .then(function (payload) {
          patientsRegistry = (payload.data || []).map(mapPatientFromApi);
          window.__PATIENTS = payload.data || [];
          if (callback) callback();
        })
        .catch(function (err) {
          console.error('fetchPatientsFromServer', err);
          if (callback) callback();
        });
    }

    function loadPatients() {
      if (Array.isArray(window.__PATIENTS)) {
        patientsRegistry = window.__PATIENTS.map(mapPatientFromApi);
        renderPatients();
        return;
      }
      fetchPatientsFromServer(function () {
        renderPatients();
      });
    }

    function getFilteredPatients(filter) {
      filter = filter || '';
      var status = (document.getElementById('patientStatusFilter') || {}).value || 'all';
      return patientsRegistry.filter(function(p) {
        var ms = !filter || p.name.indexOf(filter) !== -1 || p.phone.indexOf(filter) !== -1;
        var mst = status === 'all' || p.status === status;
        return ms && mst;
      });
    }

    function exportAppointments(type) {
      var data = getFilteredAppointments();
      var headers = ['التاريخ', 'الوقت', 'تاريخ الإضافة', 'وقت الانتظار', 'اسم المريض', 'نوع الزيارة', 'رقم الهاتف', 'جهة التعاقد / الرتبة'];
      var rows = data.map(function(a) {
        var vt = getVisitMeta(a.visitType);
        return [a.date, a.time, a.registeredAt, a.waitLabel, a.name, a.visitTypeLabel || vt.label, a.phone, a.company];
      });
      if (type === 'excel') ExportKit.toExcel('مواعيد_' + calendarView.selectedDate.replace(/\//g, '-'), headers, rows);
      else ExportKit.toPDF('مواعيد — ' + calendarView.selectedDate, headers, rows);
    }

    function exportPatients(type) {
      var data = getFilteredPatients((document.getElementById('patientSearch') || {}).value.trim());
      var headers = ['اسم المريض', 'رقم الهاتف', 'جهة التعاقد / الرتبة', 'تاريخ التسجيل', 'آخر زيارة'];
      var rows = data.map(function(p) {
        return [p.name, p.phone, p.company, p.registered, p.lastVisit];
      });
      if (type === 'excel') ExportKit.toExcel('سجل_المرضى', headers, rows);
      else ExportKit.toPDF('سجل المرضى المسجلين', headers, rows);
    }

    function getApptActionCell(a) {
      if (a.transferredToClinic || a.status === 'in_clinic') {
        return '<span style="font-size:12px;font-weight:700;color:#059669;">✅ تم التحويل للعيادة</span>';
      }
      return '<button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="transferAppointmentToClinic(' + a.id + ')">تحويل</button>';
    }

    function transferAppointmentToClinic(id) {
      var appt = appointments.find(function(a) { return a.id === id; });
      if (!appt || appt.transferredToClinic || appt.status === 'in_clinic') return;

      fetch('/reception/appointments/' + id + '/status', {
        method: 'PATCH',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ status: 'in_clinic' })
      })
        .then(function(res) {
          if (!res.ok) return res.json().then(function(j) { throw j; });
          return res.json();
        })
        .then(function(row) {
          var mapped = mapAppointmentFromApi(row);
          var idx = appointments.findIndex(function(a) { return a.id === id; });
          if (idx !== -1) appointments[idx] = mapped;
          renderAppointments();
          renderCalendar();
          renderReceptionAnalytics();
          showToast('تم تحويل ' + mapped.name + ' للعيادة');
        })
        .catch(function(err) {
          showToast((err && err.message) ? err.message : 'تعذّر تحويل الموعد للعيادة', true);
        });
    }

    function renderAppointments() {
      var filtered = getFilteredAppointments();
      var tbody = document.getElementById('appointmentsTable');
      tbody.innerHTML = filtered.map(function(a) {
        var vt = getVisitMeta(a.visitType);
        var visitLabel = a.visitTypeLabel || vt.label;
        return '<tr>' +
          '<td><strong>' + a.time + '</strong></td>' +
          '<td style="font-size:12px;white-space:nowrap;">' + a.registeredAt + '</td>' +
          '<td><span class="wait-time">' + a.waitLabel + '</span></td>' +
          '<td>' + a.name + '</td>' +
          '<td><span class="visit-tag ' + vt.tagClass + '">' + visitLabel + '</span></td>' +
          '<td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">' + a.phone + '</td>' +
          '<td>' + a.company + '</td>' +
          '<td>' + getApptActionCell(a) + '</td>' +
          '</tr>';
      }).join('') || '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد مواعيد في هذا اليوم</td></tr>';
      var ac = document.getElementById('apptCount');
      if (ac) ac.textContent = filtered.length + ' موعد';
      var ah = document.getElementById('apptHeaderCount');
      if (ah) ah.textContent = filtered.length + ' موعد';
      var title = document.getElementById('apptPanelTitle');
      if (title) title.textContent = '📅 مواعيد — ' + calendarView.selectedDate;
      if (window.TablePagination) TablePagination.refreshById('appointmentsTable');
    }

    function renderPatients(filter) {
      var filtered = getFilteredPatients(filter);
      var tbody = document.getElementById('patientsTable');
      if (!tbody) return;
      tbody.innerHTML = filtered.length
        ? filtered.map(function(p) {
            return '<tr>' +
              '<td><strong>' + p.name + '</strong></td>' +
              '<td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">' + p.phone + '</td>' +
              '<td>' + p.company + '</td>' +
              '<td>' + p.registered + '</td>' +
              '<td>' + p.lastVisit + '</td>' +
              '<td><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="openPatientFile(\'' + p.phone + '\')">عرض الملف</button></td>' +
              '</tr>';
          }).join('')
        : '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">لا يوجد مرضى مسجّلون</td></tr>';
      var countEl = document.getElementById('patientsCount');
      if (countEl) countEl.textContent = filtered.length + ' مريض';
      if (window.TablePagination) TablePagination.refreshById('patientsTable');
    }

    function getPatientVisits(patient) {
      var visits = [{ date: patient.lastVisit, action: 'زيارة', status: patient.statusLabel }];
      if (patient.status === 'quoted') {
        visits.push({ date: patient.lastVisit, action: 'عرض سعر', status: 'عرض سعر' });
      }
      if (patient.status === 'done') {
        visits.push({ date: patient.lastVisit, action: 'إغلاق ملف', status: 'مكتمل' });
      }
      var regParts = patient.registered.split('/');
      if (regParts.length === 3) {
        visits.push({ date: patient.registered, action: 'تسجيل أول', status: 'ملف جديد' });
      }
      return visits.slice(0, 4);
    }

    function openPatientFile(phone) {
      var patient = patientsRegistry.find(function(p) { return p.phone === phone; });
      if (!patient) return;
      var fileId = 'PAT-' + patient.phone.slice(-6);

      document.getElementById('patientFileTitle').textContent = '👤 ' + patient.name;
      document.getElementById('patientFileStatus').innerHTML =
        '<span class="status-badge ' + patient.status + '">' + patient.statusLabel + '</span>' +
        ' <span style="font-size:12px;color:var(--text-muted);margin-right:8px;">رقم الملف: ' + fileId + '</span>';

      document.getElementById('patientFileMeta').innerHTML =
        '<div class="item"><div class="lbl">رقم الهاتف</div><div class="val" style="direction:ltr;text-align:right;">' + patient.phone + '</div></div>' +
        (patient.patient_type === 'military'
          ? '<div class="item"><div class="lbl">الرتبة العسكرية</div><div class="val">' + (patient.rank || '—') + '</div></div>'
          : '<div class="item"><div class="lbl">جهة التعاقد</div><div class="val">' + patient.company + '</div></div>') +
        '<div class="item"><div class="lbl">تاريخ التسجيل</div><div class="val">' + patient.registered + '</div></div>' +
        '<div class="item"><div class="lbl">آخر زيارة</div><div class="val">' + patient.lastVisit + '</div></div>' +
        '<div class="item"><div class="lbl">مسجل بواسطة</div><div class="val">نورهان علي — الاستقبال</div></div>';

      document.getElementById('patientFileVisits').innerHTML = getPatientVisits(patient).map(function(v) {
        return '<tr><td>' + v.date + '</td><td>' + v.action + '</td><td>' + v.status + '</td></tr>';
      }).join('');

      document.getElementById('patientFileModal').classList.add('visible');
    }

    function closePatientFileModal() {
      document.getElementById('patientFileModal').classList.remove('visible');
    }

    function showToast(msg) {
      if (window.DashboardToast) {
        window.DashboardToast.show(msg);
        return;
      }
      var toast = document.getElementById('toast');
      toast.textContent = '✅ ' + msg;
      toast.classList.add('show');
      setTimeout(function() { toast.classList.remove('show'); }, 5000);
    }

    function deliverCase(caseId) {
      var check = BomInventory.canDeliver(caseId);
      if (!check.ok) {
        showToast('⚠️ ' + check.reason);
        return;
      }
      var c = CasesWorkflow.getById(caseId);
      if (!c) return;
      if (!confirm('تأكيد تسليم الطرف للمريض:\n' + c.patient + '؟')) return;
      var result = CasesWorkflow.onDelivered(caseId, {
        deliveredAt: TODAY_DATE,
        paid: c.paid,
        totalCost: c.totalCost
      });
      if (result && result.error) {
        showToast('⚠️ ' + result.error);
        return;
      }
      showToast('تم التسليم — ' + c.patient);
      renderDeliveryTable();
    }
    window.deliverCase = deliverCase;
    window.transferAppointmentToClinic = transferAppointmentToClinic;

    function renderDeliveryTable() {
      var tbody = document.getElementById('deliveryTable');
      var countEl = document.getElementById('deliveryReadyCount');
      if (!tbody) return;
      var ready = BomInventory.getReadyForDelivery();
      if (countEl) countEl.textContent = ready.length;
      if (!ready.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد حالات جاهزة للتسليم — BOM يجب أن تكون «تام»</td></tr>';
        return;
      }
      tbody.innerHTML = ready.map(function(c) {
        var bom = BomInventory.getByCaseId(c.id);
        var tmeta = CasesWorkflow.getPatientTypeMeta(c.patientType);
        return '<tr>' +
          '<td><strong>' + c.patient + '</strong> <span class="patient-type-badge ' + tmeta.badge + '">' + tmeta.icon + ' ' + tmeta.label + '</span></td>' +
          '<td>' + c.company + '</td>' +
          '<td>' + (c.workOrderNo || c.orderRef) + '</td>' +
          '<td><span class="stage-badge done">' + BomInventory.getStageLabel(bom ? bom.stage : 'finished') + '</span></td>' +
          '<td><button type="button" class="btn btn-primary" style="padding:8px 14px;font-size:12px" onclick="deliverCase(\'' + c.id + '\')">✅ تسليم للمريض</button></td>' +
          '</tr>';
      }).join('');
    }

    function escapeHtml(text) {
      if (text == null) return '';
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function renderSelfServiceSteps(steps) {
      return (steps || []).map(function (step, index) {
        var cls = step.status === 'done' ? 'done' : (step.status === 'current' ? 'current' : '');
        var dot = step.status === 'done' ? '✓' : String(index + 1);
        var sub = step.status === 'current' ? '← المرحلة الحالية' : (step.status === 'done' ? 'مكتمل' : '');
        return '<li class="ss-step ' + cls + '">' +
          '<span class="ss-step-dot">' + dot + '</span>' +
          '<div><div class="ss-step-label">' + escapeHtml(step.label) + '</div>' +
          (sub ? '<div class="ss-step-sub">' + sub + '</div>' : '') +
          '</div></li>';
      }).join('');
    }

    function renderSelfServiceCases(cases) {
      if (!cases || !cases.length) {
        return '<p style="font-size:13px;color:var(--text-muted)">لا توجد حالات مسجّلة بعد — المريض في مرحلة التسجيل فقط.</p>';
      }
      return cases.map(function (c) {
        return '<div class="ss-case-card">' +
          '<strong>' + escapeHtml(c.case_no || '—') + ' — ' + escapeHtml(c.stage_label) + '</strong>' +
          '<div>مرجع الطلب: ' + escapeHtml(c.order_ref || '—') + '</div>' +
          (c.work_order_no ? '<div>أمر الشغل: ' + escapeHtml(c.work_order_no) + '</div>' : '') +
          (c.bom_stage_label ? '<div>مرحلة BOM: ' + escapeHtml(c.bom_stage_label) + '</div>' : '') +
          (c.delivered_at ? '<div>تاريخ التسليم: ' + escapeHtml(c.delivered_at) + '</div>' : '') +
          '</div>';
      }).join('');
    }

    function renderSelfServiceResult(data) {
      var p = data.patient || {};
      var tracking = data.tracking || {};
      var active = data.active_case;
      var typeBadge = p.patient_type === 'military'
        ? '<span class="patient-type-badge military">🪖 عسكري</span>'
        : '<span class="patient-type-badge civilian">🌐 مدني</span>';
      var pathwayLabel = tracking.pathway === 'military' ? 'مسار عسكري' : 'مسار مدني';

      return '<div class="selfservice-result">' +
        '<div class="selfservice-row"><span>المريض</span><strong>' + escapeHtml(p.name) + ' ' + typeBadge + '</strong></div>' +
        '<div class="selfservice-row"><span>الهاتف</span><strong dir="ltr">' + escapeHtml(p.phone || '—') + '</strong></div>' +
        '<div class="selfservice-row"><span>كود المريض</span><strong>' + escapeHtml(p.patient_code || '—') + '</strong></div>' +
        (p.patient_type === 'military'
          ? (p.rank ? '<div class="selfservice-row"><span>الرتبة</span><strong>' + escapeHtml(p.rank) + '</strong></div>' : '')
          : '<div class="selfservice-row"><span>جهة التعاقد</span><strong>' + escapeHtml(p.company_name || '—') + '</strong></div>') +
        '<div class="selfservice-row"><span>تاريخ التسجيل</span><strong>' + escapeHtml(p.registered_at || '—') + '</strong></div>' +
        '<div class="selfservice-section">' +
          '<h4>📍 الحالة الحالية</h4>' +
          '<div class="selfservice-row"><span>المرحلة</span><strong>' + escapeHtml(tracking.stage_label || '—') + '</strong></div>' +
          (active && active.case_no ? '<div class="selfservice-row"><span>رقم الحالة</span><strong>' + escapeHtml(active.case_no) + '</strong></div>' : '') +
          (data.queue_position ? '<div class="selfservice-row"><span>ترتيبك في الطابور</span><strong>' + data.queue_position + '</strong></div>' : '') +
          '<div class="selfservice-row"><span>التسليم المتوقع</span><strong>' + escapeHtml(data.expected_delivery || '—') + '</strong></div>' +
          '<div class="selfservice-row"><span>المسار</span><strong>' + pathwayLabel + '</strong></div>' +
        '</div>' +
        '<div class="selfservice-section">' +
          '<h4>📊 تقدّم الرحلة</h4>' +
          '<div class="ss-progress-wrap">' +
            '<div class="ss-progress-bar"><div class="ss-progress-fill" style="width:' + (data.progress_percent || 0) + '%"></div></div>' +
            '<div class="ss-progress-label">' + (data.progress_percent || 0) + '% مكتمل</div>' +
          '</div>' +
          '<ul class="ss-steps">' + renderSelfServiceSteps(tracking.steps) + '</ul>' +
        '</div>' +
        '<div class="selfservice-section">' +
          '<h4>📁 سجل الحالات</h4>' +
          renderSelfServiceCases(data.cases) +
        '</div>' +
      '</div>';
    }

    function renderSelfService(query) {
      var resultEl = document.getElementById('ssResult');
      if (!resultEl) return;
      var q = (query || '').trim();
      if (!q) { resultEl.innerHTML = ''; return; }

      resultEl.innerHTML = '<div class="ss-loading">جاري البحث...</div>';

      fetch('/reception/selfservice/lookup?q=' + encodeURIComponent(q), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.message || 'تعذّر الاستعلام');
            return body;
          });
        })
        .then(function (data) {
          resultEl.innerHTML = renderSelfServiceResult(data);
        })
        .catch(function (err) {
          resultEl.innerHTML = '<div class="ss-error">' + escapeHtml(err.message || 'تعذّر الاستعلام') + '</div>';
        });
    }

    var btnSS = document.getElementById('btnSelfService');
    if (btnSS) btnSS.addEventListener('click', function(){ renderSelfService(document.getElementById('ssInput').value); });
    var ssInput = document.getElementById('ssInput');
    if (ssInput) ssInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') renderSelfService(ssInput.value); });

    function dashboardPageUrl(page) {
      var seg = window.location.pathname.split('/').filter(Boolean);
      return '/' + (seg[0] || 'reception') + '/' + page;
    }

    function switchTab(tabId) {
      if (!document.getElementById('tab-' + tabId)) {
        window.location.href = dashboardPageUrl(tabId);
        return;
      }
      var calWrap = document.getElementById('appointmentsCalendarWrap');
      if (calWrap) calWrap.classList.toggle('visible', tabId === 'appointments');
      if (tabId === 'delivery') renderDeliveryTable();
    }

    document.querySelectorAll('.nav-menu a[data-tab]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        var tabId = link.getAttribute('data-tab');
        if (tabId && !document.getElementById('tab-' + tabId)) {
          e.preventDefault();
          switchTab(tabId);
        }
      });
    });

    function getHourlyAppointmentsChart() {
      var hours = {};
      getSelectedDayAppointments().forEach(function(a) {
        if (a.time.indexOf(':') === -1) return;
        var h = parseInt(a.time.split(':')[0], 10);
        hours[h] = (hours[h] || 0) + 1;
      });
      var keys = Object.keys(hours).sort(function(a, b) { return parseInt(a, 10) - parseInt(b, 10); });
      var max = Math.max.apply(null, keys.map(function(k) { return hours[k]; }).concat([1]));
      return keys.map(function(h) {
        var count = hours[h];
        var sub = count === max ? '↑ الأكثر' : (count === 1 ? '↓ الأقل' : '→');
        return {
          label: h + ':00',
          value: count,
          display: count === 1 ? '1 موعد' : count + ' مواعيد',
          sub: sub
        };
      });
    }

    function renderReceptionAnalytics() {
      var dayAppts = getSelectedDayAppointments();
      var total = dayAppts.length;
      var inClinic = dayAppts.filter(function(a) { return a.status === 'in_clinic'; }).length;
      var waiting = dayAppts.filter(function(a) { return a.status === 'waiting'; }).length;

      function setStat(containerId, index, value) {
        var root = document.getElementById(containerId);
        if (!root) return;
        var values = root.querySelectorAll('.ck-stat-value');
        if (values[index]) values[index].textContent = String(value);
      }

      setStat('analytics-appointments', 0, total);
      setStat('analytics-appointments', 1, inClinic);
      setStat('analytics-appointments', 2, waiting);
    }

    function changeCalendarMonth(delta) {
      calendarView.month += delta;
      if (calendarView.month > 12) {
        calendarView.month = 1;
        calendarView.year++;
      } else if (calendarView.month < 1) {
        calendarView.month = 12;
        calendarView.year--;
      }
      clampCalendarMonth();
      syncSelectionToVisibleMonth();
      renderCalendar();
      fetchAppointmentsForSelectedDate();
    }

    function initAppointmentsCalendar() {
      var grid = document.getElementById('calendarGrid');
      if (!grid) return;

      TODAY_DATE = formatDateKey(now.getDate(), now.getMonth() + 1, now.getFullYear());
      var minDateObj = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
      MIN_SELECTABLE_DATE = formatDateKey(minDateObj.getDate(), minDateObj.getMonth() + 1, minDateObj.getFullYear());

      calendarView.year = now.getFullYear();
      calendarView.month = now.getMonth() + 1;
      calendarView.selectedDate = TODAY_DATE;

      renderCalendar();
      fetchAppointmentsForSelectedDate();
    }

    function syncAddPatientFormHeight() {
      var wrap = document.getElementById('addPatientFormWrap');
      if (!wrap || !wrap.classList.contains('open')) return;
      wrap.style.maxHeight = 'none';
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
    }

    function toggleAddPatientForm(forceOpen) {
      var wrap = document.getElementById('addPatientFormWrap');
      if (!wrap) return;
      var section = document.getElementById('addPatientSection');
      var btn = document.getElementById('btnAddPatient');
      var isOpen = wrap.classList.contains('open');
      var open = forceOpen === true ? true : (forceOpen === false ? false : !isOpen);
      wrap.classList.toggle('open', open);
      if (section) section.classList.toggle('expanded', open);
      if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        requestAnimationFrame(syncAddPatientFormHeight);
        loadContractCompanies();
        var patientForm = document.getElementById('addPatientForm');
        if (patientForm && window.DashboardValidation) {
          window.DashboardValidation.bindForm(patientForm);
        }
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function() {
          var nameEl = document.getElementById('newPatientName');
          if (nameEl) nameEl.focus();
        }, 350);
      } else {
        wrap.style.maxHeight = '';
      }
    }

    function closeAddPatientForm() {
      toggleAddPatientForm(false);
    }

    function openAddPatientForm() {
      toggleAddPatientForm(true);
    }
    window.openAddPatientForm = openAddPatientForm;

    var closeQuoteModalBtn = document.getElementById('closeQuoteModal');
    if (closeQuoteModalBtn) closeQuoteModalBtn.addEventListener('click', closeQuoteModal);
    var btnCloseQuoteModal = document.getElementById('btnCloseQuoteModal');
    if (btnCloseQuoteModal) btnCloseQuoteModal.addEventListener('click', closeQuoteModal);
    var btnPrintQuoteModal = document.getElementById('btnPrintQuoteModal');
    if (btnPrintQuoteModal) btnPrintQuoteModal.addEventListener('click', printQuoteModal);
    var quoteModal = document.getElementById('quoteModal');
    if (quoteModal) {
      quoteModal.addEventListener('click', function(e) {
        if (e.target === quoteModal) closeQuoteModal();
      });
    }

    var closePatientFileModalBtn = document.getElementById('closePatientFileModal');
    if (closePatientFileModalBtn) closePatientFileModalBtn.addEventListener('click', closePatientFileModal);
    var btnClosePatientFile = document.getElementById('btnClosePatientFile');
    if (btnClosePatientFile) btnClosePatientFile.addEventListener('click', closePatientFileModal);
    var patientFileModal = document.getElementById('patientFileModal');
    if (patientFileModal) {
      patientFileModal.addEventListener('click', function(e) {
        if (e.target === patientFileModal) closePatientFileModal();
      });
    }

    var btnAddPatient = document.getElementById('btnAddPatient');
    if (btnAddPatient) {
      btnAddPatient.addEventListener('click', function() {
        toggleAddPatientForm();
      });
    }

    var btnCancelAddPatient = document.getElementById('btnCancelAddPatient');
    if (btnCancelAddPatient) btnCancelAddPatient.addEventListener('click', closeAddPatientForm);

    // ── CSRF helper ────────────────────────────────────────────────────────
    function getCsrfToken() {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    function filterCompanyOptions(isMilitary) {
      var grpCompany = document.getElementById('grpCompany');
      if (grpCompany) grpCompany.style.display = isMilitary ? 'none' : '';
      var sel = document.getElementById('newCompanyId');
      if (!sel) return;
      if (isMilitary) {
        sel.value = '';
        return;
      }
      sel.querySelectorAll('option[data-military]').forEach(function(opt) {
        var mil = opt.getAttribute('data-military') === '1';
        opt.style.display = mil ? 'none' : '';
        opt.disabled = mil;
      });
      if (sel.value) {
        var selected = sel.querySelector('option[value="' + sel.value + '"]');
        if (selected && selected.style.display === 'none') sel.value = '';
      }
    }

    /**
     * تحميل جهات التعاقد من قاعدة البيانات (المضافة من الإدارة) — لا بيانات static.
     */
    function loadContractCompanies() {
      var sel = document.getElementById('newCompanyId');
      if (!sel) return Promise.resolve();

      var preserved = sel.value;

      return fetch('/reception/lookup/companies?all=1', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then(function (r) { return r.ok ? r.json() : { data: [] }; })
        .then(function (res) {
          var companies = res.data || [];
          sel.innerHTML = '<option value="">— اختر الجهة —</option>';
          companies.forEach(function (co) {
            var opt = document.createElement('option');
            opt.value = String(co.id);
            opt.textContent = co.name;
            opt.setAttribute('data-military', co.is_military ? '1' : '0');
            sel.appendChild(opt);
          });
          if (preserved && sel.querySelector('option[value="' + preserved + '"]')) {
            sel.value = preserved;
          }
          var patientTypeSel = document.getElementById('newPatientType');
          var isMil = patientTypeSel && patientTypeSel.value === 'military';
          filterCompanyOptions(isMil);
        })
        .catch(function () { /* يبقى على خيارات السيرفر */ });
    }

    // Initial company filter (civilian default) + load from server
    if (document.getElementById('newCompanyId')) {
      loadContractCompanies();
    }

    // ── Patient-type change ─────────────────────────────────────────────────
    var patientTypeSel = document.getElementById('newPatientType');
    if (patientTypeSel) {
      patientTypeSel.addEventListener('change', function() {
        var isMil = patientTypeSel.value === 'military';
        var grpRank = document.getElementById('grpRank');
        if (grpRank) grpRank.style.display = isMil ? '' : 'none';
        filterCompanyOptions(isMil);
        requestAnimationFrame(syncAddPatientFormHeight);
      });
      filterCompanyOptions(patientTypeSel.value === 'military');
    }

    if (document.getElementById('addPatientFormWrap')?.classList.contains('open')) {
      requestAnimationFrame(syncAddPatientFormHeight);
    }

    // ── Patient card display ─────────────────────────────────────────────────
    function patientCardPrintUrl(data) {
      if (!data) return null;
      if (data.card_print_url) return data.card_print_url;
      if (data.id) return '/reception/patients/' + data.id + '/card/print';
      return null;
    }

    function printPatientCard() {
      var url = window._patientCardPrintUrl;
      if (!url) {
        showToast('تعذّر فتح نموذج طباعة البطاقة', true);
        return;
      }
      window.open(url, '_blank', 'noopener,noreferrer');
    }
    window.printPatientCard = printPatientCard;

    function showPatientCard(data) {
      var meta = CasesWorkflow.getPatientTypeMeta(data.patientType || data.patient_type);
      document.getElementById('picType').textContent = meta.icon + ' ' + meta.label;
      document.getElementById('picName').textContent = data.name;
      document.getElementById('picId').textContent = data.patient_code || data.patientId || '—';
      var queueWrap = document.getElementById('picQueueWrap');
      var queueEl = document.getElementById('picQueue');
      if (queueEl) {
        var queueNo = data.queue_number || data.id || null;
        queueEl.textContent = queueNo != null ? queueNo : '—';
        if (queueWrap) queueWrap.style.display = queueNo != null ? '' : 'none';
      }
      var picCompany = document.getElementById('picCompany');
      if (picCompany) {
        if ((data.patientType || data.patient_type) === 'military') {
          picCompany.style.display = 'none';
          picCompany.textContent = '';
        } else {
          picCompany.style.display = '';
          picCompany.textContent = data.company || data.company_name || '—';
        }
      }
      var rankEl = document.getElementById('picRank');
      var rankText = data.rank || '';
      if ((data.patientType || data.patient_type) === 'military' && rankText) {
        rankEl.style.display = '';
        rankEl.textContent = 'الرتبة: ' + rankText;
      } else {
        rankEl.style.display = 'none';
      }
      var qrEl = document.getElementById('picQr');
      if (qrEl) {
        qrEl.innerHTML = data.qr_svg || '';
        qrEl.classList.toggle('has-svg', !!data.qr_svg);
      }
      document.getElementById('picQrText').textContent = data.tracking_url || data.tracking_uid || data.patientQr || data.patient_qr || '—';
      var card = document.getElementById('patientIdCard');
      if (card) card.setAttribute('data-type', data.patientType || data.patient_type);
      window._patientCardPrintUrl = patientCardPrintUrl(data);
      document.getElementById('patientCardModal').classList.add('visible');
    }

    function closePatientCard() {
      document.getElementById('patientCardModal').classList.remove('visible');
    }
    var btnClosePC = document.getElementById('btnClosePatientCard');
    var btnClosePCx = document.getElementById('closePatientCardModal');
    var btnPrintPC = document.getElementById('btnPrintPatientCard');
    if (btnClosePC) btnClosePC.addEventListener('click', closePatientCard);
    if (btnClosePCx) btnClosePCx.addEventListener('click', closePatientCard);
    if (btnPrintPC) btnPrintPC.addEventListener('click', printPatientCard);

    function showFormError(el, msg) {
      if (!el) { alert(msg); return; }
      el.textContent = msg;
      el.style.display = 'block';
    }

    function resetPatientForm() {
      document.getElementById('newPatientName').value = '';
      if (document.getElementById('newPhone')) document.getElementById('newPhone').value = '';
      if (document.getElementById('newNationalId')) document.getElementById('newNationalId').value = '';
      if (document.getElementById('newRankId')) document.getElementById('newRankId').value = '';
      if (document.getElementById('newCompanyId')) document.getElementById('newCompanyId').value = '';
      document.getElementById('newPatientType').value = 'civilian';
      document.getElementById('grpRank').style.display = 'none';
      filterCompanyOptions(false);
      var errorEl = document.getElementById('patientFormError');
      if (errorEl) errorEl.style.display = 'none';
    }

    var btnScanQR = document.getElementById('btnScanQR');
    if (btnScanQR) btnScanQR.addEventListener('click', function() { openQRScan(); });
    var closeQrModal = document.getElementById('closeQrModal');
    if (closeQrModal) closeQrModal.addEventListener('click', closeQRScanModal);
    var qrModal = document.getElementById('qrModal');
    if (qrModal) {
      qrModal.addEventListener('click', function(e) {
        if (e.target === qrModal) closeQRScanModal();
      });
    }

    var calPrev = document.getElementById('calPrev');
    var calNext = document.getElementById('calNext');
    var calToday = document.getElementById('calToday');
    if (calPrev) calPrev.addEventListener('click', function() { changeCalendarMonth(-1); });
    if (calNext) calNext.addEventListener('click', function() { changeCalendarMonth(1); });
    if (calToday) calToday.addEventListener('click', function() { selectCalendarDate(TODAY_DATE); });

    var apptSearchEl = document.getElementById('apptSearch');
    if (apptSearchEl) apptSearchEl.addEventListener('input', function() { renderAppointments(); });
    var apptStatusEl = document.getElementById('apptStatusFilter');
    if (apptStatusEl) apptStatusEl.addEventListener('change', function() { renderAppointments(); });
    var patientStatusEl = document.getElementById('patientStatusFilter');
    if (patientStatusEl) patientStatusEl.addEventListener('change', function() {
      renderPatients((document.getElementById('patientSearch') || {}).value.trim());
    });

    var patientSearch = document.getElementById('patientSearch');
    if (patientSearch) {
      patientSearch.addEventListener('input', function(e) {
        renderPatients(e.target.value.trim());
      });
    }

    var uploadZone = document.getElementById('uploadZone');
    var fileInput = document.getElementById('fileInput');

    if (uploadZone && fileInput) {
      uploadZone.addEventListener('click', function() { fileInput.click(); });

      uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
      });

      uploadZone.addEventListener('dragleave', function() {
        uploadZone.classList.remove('dragover');
      });

      uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) simulateOCR();
      });

      fileInput.addEventListener('change', function() {
        if (fileInput.files.length) simulateOCR();
      });
    }

    function simulateOCR() {
      document.getElementById('ocrLoading').classList.add('visible');
      document.getElementById('ocrResults').classList.remove('visible');
      document.getElementById('ocrForm').style.display = 'none';
      document.getElementById('ocrActions').style.display = 'none';
      uploadZone.style.display = 'none';

      setTimeout(function() {
        document.getElementById('ocrLoading').classList.remove('visible');

        document.getElementById('ocrName').textContent = '—';
        document.getElementById('ocrAmount').textContent = '—';
        document.getElementById('ocrCompany').textContent = '—';
        document.getElementById('ocrRef').textContent = '—';
        document.getElementById('ocrDate').textContent = '—';

        document.getElementById('confirmName').value = '';
        document.getElementById('confirmAmount').value = '';

        document.getElementById('ocrResults').classList.add('visible');
        document.getElementById('ocrForm').style.display = 'grid';
        document.getElementById('ocrActions').style.display = 'flex';
      }, 2200);
    }

    var btnBypass = document.getElementById('btnBypass');
    if (btnBypass) btnBypass.addEventListener('click', function() {
      var patientName = document.getElementById('confirmName').value || 'مريض';
      var amountStr = document.getElementById('confirmAmount').value || '0';
      var amount = parseInt(String(amountStr).replace(/\D/g, ''), 10) || 0;
      var company = document.getElementById('ocrCompany').textContent || '—';
      var orderRef = 'OCR-' + TODAY_DATE.replace(/\//g, '');
      var pqMatch = PricingQueue.getAll().find(function(p) {
        return patientName.indexOf(p.patient.split(' ')[0]) !== -1 ||
          p.patient.indexOf(patientName.split(' ')[0]) !== -1;
      });
      CasesWorkflow.onApprovalConfirmed({
        patient: patientName,
        company: company,
        orderRef: orderRef,
        approvalDate: TODAY_DATE,
        totalCost: amount,
        manufacturingStage: 'issue',
        path: 'ocr_bypass',
        recommendations: pqMatch ? (pqMatch.recommendations || []).slice() : []
      });
      showToast('تم تأكيد الموافقة — الحالة انتقلت إلى تحت التنفيذ');
      resetOCR();
    });

    window.addEventListener('storage', function(e) {
      if (e.key === PricingQueue.STORAGE_KEY) {
        syncPricingQueueToQuotes();
        renderQuoteTable();
      }
      if (e.key === CasesWorkflow.STORAGE_KEY || e.key === BomInventory.STORAGE_KEY) {
        renderDeliveryTable();
      }
    });

    var btnResetOcr = document.getElementById('btnResetOcr');
    if (btnResetOcr) btnResetOcr.addEventListener('click', resetOCR);

    function resetOCR() {
      if (!uploadZone || !fileInput) return;
      uploadZone.style.display = 'block';
      document.getElementById('ocrResults').classList.remove('visible');
      document.getElementById('ocrForm').style.display = 'none';
      document.getElementById('ocrActions').style.display = 'none';
      fileInput.value = '';
    }

    // ══════════════════════════════════════════════════════════════════
    // OCR Approval Modal — رفع خطاب موافقة الجهة الضامنة (Civilian only)
    // ══════════════════════════════════════════════════════════════════

    var _ocrCurrentQuote = null;
    var _ocrStoredPath   = null;
    var _ocrLetterDate   = null;

    function ocrShowStep(step) {
      ['ocrStep1','ocrStep2','ocrStep3','ocrStep4'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
      });
      var target = document.getElementById(step);
      if (target) target.style.display = 'block';
    }

    function openOcrApprovalModal(quote) {
      if (!quote) return;
      _ocrCurrentQuote = quote;
      _ocrStoredPath   = null;
      _ocrLetterDate   = null;

      var refEl = document.getElementById('ocrQuoteRef');
      if (refEl) refEl.textContent = quote.id + ' — ' + quote.patient + ' / ' + quote.company;

      var fi = document.getElementById('ocrFileInput');
      if (fi) fi.value = '';

      var errEl = document.getElementById('ocrError');
      if (errEl) errEl.style.display = 'none';

      ocrShowStep('ocrStep1');

      var modal = document.getElementById('ocrApprovalModal');
      if (modal) modal.style.display = 'flex';
    }

    function closeOcrApprovalModal() {
      var modal = document.getElementById('ocrApprovalModal');
      if (modal) modal.style.display = 'none';
      _ocrCurrentQuote = null;
      _ocrStoredPath   = null;
      _ocrLetterDate   = null;
    }

    function handleOcrFileDrop(event) {
      var files = event.dataTransfer && event.dataTransfer.files;
      if (files && files.length > 0) processOcrFile(files[0]);
    }

    function isAllowedApprovalLetter(file) {
      if (!file) return false;
      var type = (file.type || '').toLowerCase();
      if (type.indexOf('image/') === 0 || type === 'application/pdf') return true;
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif', 'ico'];
      return imageExts.indexOf(ext) !== -1 || ext === 'pdf';
    }

    function processOcrFile(file) {
      if (!file || !_ocrCurrentQuote) return;

      if (!isAllowedApprovalLetter(file)) {
        alert('نوع الملف غير مدعوم. يُرجى اختيار صورة بأي صيغة مدعومة أو PDF.');
        return;
      }
      if (file.size > 10 * 1024 * 1024) {
        alert('حجم الملف يتجاوز 10 ميجا.');
        return;
      }

      ocrShowStep('ocrStep2');

      var formData = new FormData();
      formData.append('quote_no',    _ocrCurrentQuote.id);
      formData.append('letter_file', file);

      var csrfInput = document.querySelector('meta[name="csrf-token"]');
      var csrf      = csrfInput ? csrfInput.getAttribute('content') : '';

      fetch('/reception/ocr/extract', {
        method: 'POST',
        headers: {
          'Accept':           'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN':     csrf
        },
        credentials: 'same-origin',
        body: formData
      })
        .then(function (r) {
          return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function (res) {
          _ocrStoredPath = res.stored_path || null;

          var extracted = res.extracted || {};
          var nameEl    = document.getElementById('ocrConfirmName');
          var amountEl  = document.getElementById('ocrConfirmAmount');
          var companyEl = document.getElementById('ocrConfirmCompany');
          var refEl2    = document.getElementById('ocrLetterRef');

          if (nameEl)    nameEl.value    = extracted.patient_name    || (_ocrCurrentQuote ? _ocrCurrentQuote.patient : '');
          if (amountEl)  amountEl.value  = extracted.approved_amount != null ? extracted.approved_amount : (_ocrCurrentQuote ? _ocrCurrentQuote.total : '');
          if (companyEl) companyEl.value = extracted.company_name    || (_ocrCurrentQuote ? _ocrCurrentQuote.company : '');
          if (refEl2)    refEl2.value    = extracted.letter_ref      || '';

          _ocrLetterDate = extracted.letter_date || null;

          var errEl = document.getElementById('ocrError');
          if (errEl) errEl.style.display = 'none';

          ocrShowStep('ocrStep3');
        })
        .catch(function (err) {
          ocrShowStep('ocrStep1');
          var msg = (err && err.message) ? err.message : 'تعذّر قراءة الملف — تأكد من وضوح الصورة أو جرّب PDF.';
          if (window.DashboardToast) {
            window.DashboardToast.show(msg, { isError: true });
          } else {
            alert(msg);
          }
        });
    }

    function confirmOcrApproval() {
      if (!_ocrCurrentQuote) return;

      var nameEl    = document.getElementById('ocrConfirmName');
      var amountEl  = document.getElementById('ocrConfirmAmount');
      var companyEl = document.getElementById('ocrConfirmCompany');
      var refEl     = document.getElementById('ocrLetterRef');
      var errEl     = document.getElementById('ocrError');
      var btn       = document.getElementById('btnConfirmOcr');

      var name    = nameEl    ? nameEl.value.trim()    : '';
      var amount  = amountEl  ? amountEl.value.trim()  : '';
      var company = companyEl ? companyEl.value.trim() : '';
      var ref     = refEl     ? refEl.value.trim()     : '';

      if (!name || !amount || isNaN(parseFloat(amount))) {
        if (errEl) { errEl.textContent = 'يرجى التحقق من اسم المريض والمبلغ المالي.'; errEl.style.display = 'block'; }
        return;
      }

      if (errEl) errEl.style.display = 'none';
      if (btn) { btn.disabled = true; btn.textContent = 'جاري الاعتماد...'; }

      var csrfInput = document.querySelector('meta[name="csrf-token"]');
      var csrf      = csrfInput ? csrfInput.getAttribute('content') : '';

      fetch('/reception/ocr/process', {
        method: 'POST',
        headers: {
          'Accept':           'application/json',
          'Content-Type':     'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN':     csrf
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          quote_no:        _ocrCurrentQuote.id,
          patient_name:    name,
          approved_amount: parseFloat(amount),
          company_name:    company,
          letter_ref:      ref,
          letter_date:     _ocrLetterDate || null,
          letter_path:     _ocrStoredPath || null
        })
      })
        .then(function (r) {
          return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function (res) {
          var woText    = document.getElementById('ocrSuccessWO');
          var succText  = document.getElementById('ocrSuccessText');
          if (woText)   woText.textContent   = '📋 أمر التشغيل: ' + (res.work_order_no || '—');
          if (succText) succText.textContent = 'تم اعتماد عرض السعر ' + _ocrCurrentQuote.id + ' للمريض ' + name;

          var q = quotations.find(function(x) { return x.id === _ocrCurrentQuote.id; });
          if (q) {
            q.status      = 'approved';
            q.statusLabel = 'معتمد — تم توليد أمر الشغل';
          }
          renderQuoteTable();
          renderQuoteAnalytics();

          ocrShowStep('ocrStep4');
        })
        .catch(function (err) {
          var msg = (err && err.message) ? err.message : 'تعذّر إتمام الاعتماد المالي.';
          if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
          if (btn) { btn.disabled = false; btn.textContent = '✅ تأكيد واعتماد مالي — توليد أمر الشغل'; }
        });
    }

    window.openOcrApprovalModal = openOcrApprovalModal;

    // Bind OCR modal events
    (function () {
      var fileInput = document.getElementById('ocrFileInput');
      if (fileInput) {
        fileInput.addEventListener('change', function () {
          if (fileInput.files && fileInput.files[0]) processOcrFile(fileInput.files[0]);
        });
      }

      var closeBtn = document.getElementById('btnCloseOcrModal');
      if (closeBtn) closeBtn.addEventListener('click', closeOcrApprovalModal);

      var resetBtn = document.getElementById('btnResetOcrModal');
      if (resetBtn) resetBtn.addEventListener('click', function () {
        var fi = document.getElementById('ocrFileInput');
        if (fi) fi.value = '';
        _ocrStoredPath = null;
        _ocrLetterDate = null;
        ocrShowStep('ocrStep1');
      });

      var confirmBtn = document.getElementById('btnConfirmOcr');
      if (confirmBtn) confirmBtn.addEventListener('click', confirmOcrApproval);

      var successBtn = document.getElementById('btnCloseOcrSuccess');
      if (successBtn) successBtn.addEventListener('click', closeOcrApprovalModal);
    })();

    initAppointmentsCalendar();

    syncPricingQueueToQuotes();
    if (document.getElementById('patientsTable')) loadPatients();
    renderQuoteTable();
    renderDeliveryTable();
    fetchServerQuotes();

    var quoteSearch = document.getElementById('quoteSearch');
    if (quoteSearch) {
      quoteSearch.addEventListener('input', function (e) {
        quoteSearchTerm = e.target.value.trim();
        renderQuoteTable();
      });
      quoteSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          handleQuoteBarcodeScan(e.target.value);
        }
      });
      if (document.body.dataset.activePage === 'quote') {
        quoteSearch.focus();
      }
    }

    if (window.__SHOW_PATIENT_CARD_ID) {
      fetch('/reception/patients/' + window.__SHOW_PATIENT_CARD_ID, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function(r) { return r.ok ? r.json() : null; })
        .then(function(data) { if (data) showPatientCard(data); })
        .catch(function() {});
    }
