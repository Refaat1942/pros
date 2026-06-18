    StockCatalog.ensureSeeded();
    CasesWorkflow.ensureSeeded();
    PricingQueue.ensureSeeded();
    BomInventory.ensureSeeded();
    var TODAY_DATE = '08/06/2026';

    var calendarView = {
      year: 2026,
      month: 6,
      selectedDate: TODAY_DATE
    };

    var AR_MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    var VISIT_TYPES = {
      exam: { label: 'كشف أولي', dotClass: 'cal-dot-exam', tagClass: 'visit-exam' },
      followup: { label: 'متابعة', dotClass: 'cal-dot-followup', tagClass: 'visit-followup' },
      fitting: { label: 'تركيب', dotClass: 'cal-dot-fitting', tagClass: 'visit-fitting' },
      delivery: { label: 'استلام', dotClass: 'cal-dot-delivery', tagClass: 'visit-delivery' },
      review: { label: 'مراجعة', dotClass: 'cal-dot-review', tagClass: 'visit-review' }
    };

    var appointments = [
      { time: '08:00', date: '08/06/2026', visitType: 'followup', name: 'محمود عبد الرحمن', phone: '01012345678', company: 'التأمين الوطني', status: 'in_clinic', statusLabel: 'في العيادة', transferredToClinic: true },
      { time: '08:30', date: '08/06/2026', visitType: 'exam', name: 'فاطمة حسين محمد', phone: '01123456789', company: 'التأمين الصحي', status: 'waiting', statusLabel: 'انتظار' },
      { time: '09:00', date: '08/06/2026', visitType: 'exam', name: 'عبدالله سامي رشاد', phone: '01098765432', company: 'ذوي الإعاقة', status: 'waiting', statusLabel: 'انتظار' },
      { time: '09:30', date: '08/06/2026', visitType: 'review', name: 'مريم خالد إبراهيم', phone: '01234567890', company: 'مصر للتأمين', status: 'quoted', statusLabel: 'عرض سعر' },
      { time: '10:00', date: '08/06/2026', visitType: 'fitting', name: 'يوسف عمر محسن', phone: '01087654321', company: 'الدفاع المدني', status: 'waiting', statusLabel: 'انتظار' },
      { time: '10:30', date: '08/06/2026', visitType: 'delivery', name: 'سارة أحمد فؤاد', phone: '01156789012', company: 'التأمين الوطني', status: 'done', statusLabel: 'مكتمل' },
      { time: '11:30', date: '08/06/2026', visitType: 'delivery', name: 'منى إبراهيم حسن', phone: '01055667788', company: 'التأمين الوطني', status: 'waiting', statusLabel: 'جاهز للاستلام' },
      { time: '11:00', date: '08/06/2026', visitType: 'followup', name: 'كريم محمد علي', phone: '01065432198', company: 'التأمين الصحي', status: 'waiting', statusLabel: 'انتظار' },
      { time: '10:00', date: '05/06/2026', visitType: 'followup', name: 'هدى محمود سعيد', phone: '01011223344', company: 'ذوي الإعاقة', status: 'done', statusLabel: 'مكتمل' },
      { time: '11:30', date: '05/06/2026', visitType: 'exam', name: 'أحمد فاروق نبيل', phone: '01199887766', company: 'مصر للتأمين', status: 'waiting', statusLabel: 'انتظار' },
      { time: '09:00', date: '06/06/2026', visitType: 'fitting', name: 'ليلى حسام الدين', phone: '01277665544', company: 'التأمين الوطني', status: 'waiting', statusLabel: 'انتظار' },
      { time: '14:00', date: '06/06/2026', visitType: 'review', name: 'محمود عبد الرحمن', phone: '01012345678', company: 'التأمين الوطني', status: 'done', statusLabel: 'مكتمل' },
      { time: '10:30', date: '07/06/2026', visitType: 'delivery', name: 'مريم خالد إبراهيم', phone: '01234567890', company: 'مصر للتأمين', status: 'done', statusLabel: 'مكتمل' },
      { time: '09:30', date: '09/06/2026', visitType: 'exam', name: 'كريم محمد علي', phone: '01065432198', company: 'التأمين الصحي', status: 'waiting', statusLabel: 'انتظار' },
      { time: '11:00', date: '09/06/2026', visitType: 'fitting', name: 'فاطمة حسين محمد', phone: '01123456789', company: 'التأمين الصحي', status: 'waiting', statusLabel: 'انتظار' },
      { time: '08:30', date: '10/06/2026', visitType: 'followup', name: 'عبدالله سامي رشاد', phone: '01098765432', company: 'ذوي الإعاقة', status: 'waiting', statusLabel: 'انتظار' },
      { time: '10:00', date: '10/06/2026', visitType: 'exam', name: 'سارة أحمد فؤاد', phone: '01156789012', company: 'التأمين الوطني', status: 'waiting', statusLabel: 'انتظار' },
      { time: '14:30', date: '10/06/2026', visitType: 'review', name: 'يوسف عمر محسن', phone: '01087654321', company: 'الدفاع المدني', status: 'quoted', statusLabel: 'عرض سعر' },
      { time: '09:00', date: '12/06/2026', visitType: 'delivery', name: 'هدى محمود سعيد', phone: '01011223344', company: 'ذوي الإعاقة', status: 'waiting', statusLabel: 'انتظار' }
    ];

    var quotations = [
      {
        id: 'QT-2026-0847',
        orderRef: 'ORD-2026-0847',
        patient: 'محمود عبد الرحمن أحمد',
        company: 'شركة التأمين الوطني',
        date: '08/06/2026',
        status: 'approved',
        statusLabel: 'معتمد',
        items: [
          { name: 'ركبة هيدروليكية — ITM-001', qty: 1, amount: 95000 },
          { name: 'قدم Carbon Spring — ITM-003', qty: 1, amount: 55000 },
          { name: 'بطانة Silicone — ITM-004', qty: 1, amount: 12000 }
        ],
        total: 162000
      },
      {
        id: 'QT-2026-0845',
        orderRef: 'ORD-2026-0845',
        patient: 'فاطمة حسين محمد',
        company: 'هيئة التأمين الصحي',
        date: '08/06/2026',
        status: 'pending',
        statusLabel: 'بانتظار الاعتماد',
        items: [
          { name: 'ركبة Polycentric — ITM-002', qty: 1, amount: 72000 },
          { name: 'Pin Lock — ITM-006', qty: 1, amount: 5800 }
        ],
        total: 77800
      },
      {
        id: 'QT-2026-0839',
        orderRef: 'ORD-2026-0839',
        patient: 'مريم خالد إبراهيم',
        company: 'شركة مصر للتأمين',
        date: '07/06/2026',
        status: 'approved',
        statusLabel: 'معتمد',
        items: [
          { name: 'قدم Carbon Spring — ITM-003', qty: 1, amount: 55000 },
          { name: 'جوارب تجويف — ITM-009', qty: 1, amount: 450 }
        ],
        total: 55450
      }
    ];

    var quoteSearchTerm = '';

    var patientsRegistry = [
      { name: 'محمود عبد الرحمن أحمد', phone: '01012345678', company: 'التأمين الوطني', registered: '15/03/2024', lastVisit: '08/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'فاطمة حسين محمد', phone: '01123456789', company: 'التأمين الصحي', registered: '22/01/2025', lastVisit: '08/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'عبدالله سامي رشاد', phone: '01098765432', company: 'ذوي الإعاقة', registered: '10/11/2025', lastVisit: '08/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'مريم خالد إبراهيم', phone: '01234567890', company: 'مصر للتأمين', registered: '05/08/2024', lastVisit: '07/06/2026', status: 'quoted', statusLabel: 'عرض سعر' },
      { name: 'يوسف عمر محسن', phone: '01087654321', company: 'الدفاع المدني', registered: '18/02/2025', lastVisit: '05/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'سارة أحمد فؤاد', phone: '01156789012', company: 'التأمين الوطني', registered: '30/06/2024', lastVisit: '08/06/2026', status: 'done', statusLabel: 'مكتمل' },
      { name: 'كريم محمد علي', phone: '01065432198', company: 'التأمين الصحي', registered: '12/09/2025', lastVisit: '08/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'هدى محمود سعيد', phone: '01011223344', company: 'ذوي الإعاقة', registered: '20/04/2024', lastVisit: '01/06/2026', status: 'active', statusLabel: 'نشط' },
      { name: 'أحمد فاروق نبيل', phone: '01199887766', company: 'مصر للتأمين', registered: '08/12/2023', lastVisit: '28/05/2026', status: 'inactive', statusLabel: 'غير نشط' },
      { name: 'ليلى حسام الدين', phone: '01277665544', company: 'التأمين الوطني', registered: '14/07/2025', lastVisit: '06/06/2026', status: 'active', statusLabel: 'نشط' }
    ];

    function formatQuoteAmount(n) {
      return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function syncPricingQueueToQuotes() {
      PricingQueue.getAll().filter(function(p) { return p.statusKey === 'sent'; }).forEach(function(p) {
        var quoteId = p.id.replace('QT-PENDING', 'QT-2026');
        var existing = quotations.find(function(q) { return q.id === quoteId || q.orderRef === p.orderRef; });
        var total = PricingQueue.estimateTotal(p.recommendations);
        var items = (p.recommendations || []).map(function(r) {
          var stock = PricingQueue.findStockItem(r.name, r.code);
          var unit = PricingQueue.highestUnitPrice(stock);
          var qty = r.qty || 1;
          return { name: r.name + (r.code ? ' — ' + r.code : ''), qty: qty, amount: unit * qty };
        });
        if (existing) {
          if (existing.status !== 'issued') {
            existing.status = 'approved';
            existing.statusLabel = 'معتمد — جاهز للطباعة';
          }
          existing.total = total;
          existing.items = items;
        } else {
          quotations.unshift({
            id: quoteId,
            orderRef: p.orderRef,
            patient: p.patient,
            company: p.company,
            date: p.approvedAt ? p.approvedAt.split(' ')[0] : TODAY_DATE,
            status: 'approved',
            statusLabel: 'معتمد — جاهز للطباعة',
            items: items,
            total: total
          });
        }
      });
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

    function getFilteredQuotations() {
      return quotations.filter(function(q) {
        if (!quoteSearchTerm) return true;
        return q.id.indexOf(quoteSearchTerm) !== -1 ||
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

    function openQuoteModal(id) {
      var quote = quotations.find(function(q) { return q.id === id; });
      if (!quote) return;
      document.getElementById('quoteModalTitle').textContent = '🧾 ' + quote.id + ' — ' + quote.patient;
      document.getElementById('quoteModalBody').innerHTML = getQuoteDocumentHtml(quote);
      drawQR(quote.id);
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
      tbody.innerHTML = filtered.map(function(q) {
        return '<tr>' +
          '<td><strong style="color:var(--primary);">' + q.id + '</strong></td>' +
          '<td><strong>' + q.patient + '</strong></td>' +
          '<td>' + q.company + '</td>' +
          '<td>' + q.date + '</td>' +
          '<td><strong>' + formatQuoteAmount(q.total) + ' ج.م</strong></td>' +
          '<td><span class="quote-status-tag ' + q.status + '">' + q.statusLabel + '</span></td>' +
          '<td><button type="button" class="btn btn-primary" style="padding:6px 14px;font-size:12px;" onclick="openQuoteModal(\'' + q.id + '\')">عرض السعر</button></td>' +
          '</tr>';
      }).join('') || '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد عروض مطابقة</td></tr>';

      document.getElementById('quoteListCount').textContent = quotations.length;
      document.getElementById('quoteFilterCount').textContent = filtered.length + ' عروض';
    }

    function pad2(n) { return String(n).padStart(2, '0'); }

    function formatDateKey(day, month, year) {
      return pad2(day) + '/' + pad2(month) + '/' + year;
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
      calendarView.selectedDate = dateStr;
      var parts = dateStr.split('/');
      calendarView.month = parseInt(parts[1], 10);
      calendarView.year = parseInt(parts[2], 10);
      renderCalendar();
      renderAppointments();
      renderReceptionAnalytics();
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
          selectCalendarDate(btn.getAttribute('data-date'));
        });
      });
    }

    function buildCalDayCell(dayNum, dateKey, otherMonth) {
      var cls = 'cal-day' + (otherMonth ? ' other-month' : '');
      if (dateKey === TODAY_DATE) cls += ' today';
      if (dateKey === calendarView.selectedDate) cls += ' selected';

      return '<button type="button" class="' + cls + '" data-date="' + dateKey + '">' +
        '<span class="cal-day-num">' + dayNum + '</span></button>';
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
      var headers = ['التاريخ', 'الوقت', 'اسم المريض', 'نوع الزيارة', 'رقم الهاتف', 'جهة التعاقد', 'الحالة'];
      var rows = data.map(function(a) {
        var vt = getVisitMeta(a.visitType);
        return [a.date, a.time, a.name, vt.label, a.phone, a.company, a.statusLabel];
      });
      if (type === 'excel') ExportKit.toExcel('مواعيد_' + calendarView.selectedDate.replace(/\//g, '-'), headers, rows);
      else ExportKit.toPDF('مواعيد — ' + calendarView.selectedDate, headers, rows);
    }

    function exportPatients(type) {
      var data = getFilteredPatients((document.getElementById('patientSearch') || {}).value.trim());
      var headers = ['اسم المريض', 'رقم الهاتف', 'جهة التعاقد', 'تاريخ التسجيل', 'آخر زيارة', 'الحالة'];
      var rows = data.map(function(p) {
        return [p.name, p.phone, p.company, p.registered, p.lastVisit, p.statusLabel];
      });
      if (type === 'excel') ExportKit.toExcel('سجل_المرضى', headers, rows);
      else ExportKit.toPDF('سجل المرضى المسجلين', headers, rows);
    }

    function getApptActionCell(a) {
      if (a.transferredToClinic) {
        return '<span style="font-size:12px;font-weight:700;color:#059669;">✅ تم التحويل للعيادة</span>';
      }
      return '<button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="transferAppointmentToClinic(\'' + a.phone + '\')">تحويل</button>';
    }

    function transferAppointmentToClinic(phone) {
      var appt = appointments.find(function(a) { return a.phone === phone; });
      if (!appt || appt.transferredToClinic) return;
      appt.transferredToClinic = true;
      if (appt.status === 'waiting') {
        appt.status = 'in_clinic';
        appt.statusLabel = 'في العيادة';
      }
      renderAppointments();
      renderCalendar();
      renderReceptionAnalytics();
      showToast('تم تحويل ' + appt.name + ' للعيادة');
    }

    function renderAppointments() {
      var filtered = getFilteredAppointments();
      var tbody = document.getElementById('appointmentsTable');
      tbody.innerHTML = filtered.map(function(a) {
        var vt = getVisitMeta(a.visitType);
        return '<tr>' +
          '<td><strong>' + a.time + '</strong></td>' +
          '<td>' + a.name + '</td>' +
          '<td><span class="visit-tag ' + vt.tagClass + '">' + vt.label + '</span></td>' +
          '<td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">' + a.phone + '</td>' +
          '<td>' + a.company + '</td>' +
          '<td><span class="status-badge ' + a.status + '">' + a.statusLabel + '</span></td>' +
          '<td>' + getApptActionCell(a) + '</td>' +
          '</tr>';
      }).join('') || '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد مواعيد في هذا اليوم</td></tr>';
      var ac = document.getElementById('apptCount');
      if (ac) ac.textContent = filtered.length + ' موعد';
      var ah = document.getElementById('apptHeaderCount');
      if (ah) ah.textContent = filtered.length + ' موعد';
      var title = document.getElementById('apptPanelTitle');
      if (title) title.textContent = '📅 مواعيد — ' + calendarView.selectedDate;
    }

    function renderPatients(filter) {
      var filtered = getFilteredPatients(filter);
      var tbody = document.getElementById('patientsTable');
      if (!tbody) return;
      tbody.innerHTML = filtered.map(function(p) {
        return '<tr>' +
          '<td><strong>' + p.name + '</strong></td>' +
          '<td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">' + p.phone + '</td>' +
          '<td>' + p.company + '</td>' +
          '<td>' + p.registered + '</td>' +
          '<td>' + p.lastVisit + '</td>' +
          '<td><span class="status-badge ' + p.status + '">' + p.statusLabel + '</span></td>' +
          '<td><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="openPatientFile(\'' + p.phone + '\')">عرض الملف</button></td>' +
          '</tr>';
      }).join('');
      document.getElementById('patientsCount').textContent = filtered.length + ' مريض';
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
        '<div class="item"><div class="lbl">جهة التعاقد</div><div class="val">' + patient.company + '</div></div>' +
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
      var toast = document.getElementById('toast');
      toast.textContent = '✅ ' + msg;
      toast.classList.add('show');
      setTimeout(function() { toast.classList.remove('show'); }, 3500);
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

    function renderSelfService(query) {
      var resultEl = document.getElementById('ssResult');
      if (!resultEl) return;
      var q = (query || '').trim();
      if (!q) { resultEl.innerHTML = ''; return; }
      var all = CasesWorkflow.getAll();
      var c = CasesWorkflow.getByPatientId(q) ||
        CasesWorkflow.getByPatientQr(q) ||
        all.find(function(x){ return x.patient && x.patient.indexOf(q) !== -1; });
      if (!c) {
        resultEl.innerHTML = '<div class="selfservice-result" style="text-align:center;color:var(--text-muted)">لا توجد حالة مطابقة لـ «' + q + '»</div>';
        return;
      }
      var queueAhead = all.filter(function(x){
        return x.stageKey === c.stageKey && x.stageIndex === c.stageIndex &&
          (x.createdAt < c.createdAt) && x.id !== c.id;
      }).length;
      var tmeta = CasesWorkflow.getPatientTypeMeta(c.patientType);
      var bom = (typeof BomInventory !== 'undefined') ? BomInventory.getByCaseId(c.id) : null;
      var expected = c.stageKey === 'delivered' ? (c.deliveredAt || '—')
        : (c.stageKey === 'manufacturing' ? 'خلال 3–5 أيام عمل' : 'بعد اعتماد الطلب');
      resultEl.innerHTML =
        '<div class="selfservice-result">' +
          '<div class="selfservice-row"><span>المريض</span><strong>' + c.patient + ' <span class="patient-type-badge ' + tmeta.badge + '">' + tmeta.icon + ' ' + tmeta.label + '</span></strong></div>' +
          '<div class="selfservice-row"><span>Patient ID</span><strong>' + c.patientId + '</strong></div>' +
          '<div class="selfservice-row"><span>حالة الطلب الحالية</span><strong>' + (c.stageLabel || CasesWorkflow.getStageLabel(c.stageKey)) + '</strong></div>' +
          (bom ? '<div class="selfservice-row"><span>مرحلة التصنيع</span><strong>' + BomInventory.getStageLabel(bom.stage) + '</strong></div>' : '') +
          '<div class="selfservice-row"><span>أمامك في الطابور</span><strong>' + queueAhead + ' حالة</strong></div>' +
          '<div class="selfservice-row"><span>الموعد المتوقع للتسليم</span><strong>' + expected + '</strong></div>' +
        '</div>';
    }

    var btnSS = document.getElementById('btnSelfService');
    if (btnSS) btnSS.addEventListener('click', function(){ renderSelfService(document.getElementById('ssInput').value); });
    var ssInput = document.getElementById('ssInput');
    if (ssInput) ssInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') renderSelfService(ssInput.value); });

    function switchTab(tabId) {
      document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.toggle('active', content.id === 'tab-' + tabId);
      });
      document.querySelectorAll('.nav-menu a[data-tab]').forEach(function(link) {
        link.classList.toggle('active', link.getAttribute('data-tab') === tabId);
      });
      var calWrap = document.getElementById('appointmentsCalendarWrap');
      if (calWrap) calWrap.classList.toggle('visible', tabId === 'appointments');
      if (tabId === 'delivery') renderDeliveryTable();
    }

    document.querySelectorAll('.nav-menu a[data-tab]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        switchTab(link.getAttribute('data-tab'));
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
      var hourlyItems = getHourlyAppointmentsChart();
      var hourlyTotal = hourlyItems.reduce(function(s, i) { return s + i.value; }, 0);
      var hourlyPeak = hourlyItems.reduce(function(a, b) { return a.value >= b.value ? a : b; }, hourlyItems[0] || { label: '—', value: 0 });
      ChartKit.mount('analytics-reception-main', {
        stats: [
          { icon: '📅', label: 'مواعيد اليوم', value: dayAppts.length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏳', label: 'انتظار', value: dayAppts.filter(function(a){return a.status==='waiting';}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '👤', label: 'مرضى', value: patientsRegistry.length, color: '#7c3aed', bg: 'rgba(124,58,237,0.1)' },
          { icon: '🧾', label: 'عروض سعر', value: dayAppts.filter(function(a){return a.status==='quoted';}).length, bg: 'rgba(5,150,105,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'حالة المواعيد', large: true, items: [
            { label: 'انتظار', value: dayAppts.filter(function(a){return a.status==='waiting';}).length, display: dayAppts.filter(function(a){return a.status==='waiting';}).length + ' موعد', color: '#d97706' },
            { label: 'عيادة', value: dayAppts.filter(function(a){return a.status==='in_clinic';}).length, display: dayAppts.filter(function(a){return a.status==='in_clinic';}).length + ' موعد', color: '#0e7490' },
            { label: 'عرض سعر', value: dayAppts.filter(function(a){return a.status==='quoted';}).length, display: dayAppts.filter(function(a){return a.status==='quoted';}).length + ' موعد', color: '#7c3aed' },
            { label: 'مكتمل', value: dayAppts.filter(function(a){return a.status==='done';}).length, display: dayAppts.filter(function(a){return a.status==='done';}).length + ' موعد', color: '#059669' }
          ], summary: [
            { label: 'إجمالي اليوم', value: dayAppts.length + ' موعد' },
            { label: 'انتظار', value: dayAppts.filter(function(a){return a.status==='waiting';}).length + ' موعد', color: '#d97706' },
            { label: 'مكتمل', value: dayAppts.filter(function(a){return a.status==='done';}).length + ' موعد', color: '#059669' }
          ]},
          { type: 'column', title: 'مواعيد اليوم — حسب الساعة', color: '#059669', unit: 'count', items: hourlyItems,
            footer: 'إجمالي: <strong>' + hourlyTotal + ' موعد</strong> · ذروة: <strong>' + hourlyPeak.label + ' (' + hourlyPeak.value + ')</strong> · ساعات نشطة: <strong>' + hourlyItems.length + '</strong>' }
        ]
      });
      ChartKit.mount('analytics-appointments', {
        stats: [
          { icon: '📅', label: 'إجمالي', value: dayAppts.length, bg: 'rgba(5,150,105,0.1)' },
          { icon: '🏥', label: 'في العيادة', value: dayAppts.filter(function(a){return a.status==='in_clinic';}).length, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '⏳', label: 'انتظار', value: dayAppts.filter(function(a){return a.status==='waiting';}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '✅', label: 'مكتمل', value: dayAppts.filter(function(a){return a.status==='done';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'أنواع الزيارات', color: '#059669', items: [
            { label: 'كشف أولي', value: dayAppts.filter(function(a){return a.visitType==='exam';}).length },
            { label: 'متابعة', value: dayAppts.filter(function(a){return a.visitType==='followup';}).length },
            { label: 'تركيب', value: dayAppts.filter(function(a){return a.visitType==='fitting';}).length },
            { label: 'استلام', value: dayAppts.filter(function(a){return a.visitType==='delivery';}).length },
            { label: 'مراجعة', value: dayAppts.filter(function(a){return a.visitType==='review';}).length }
          ].filter(function(i){ return i.value > 0; }) },
          { type: 'donut', title: 'الحالة', items: [
            { label: 'انتظار', value: dayAppts.filter(function(a){return a.status==='waiting';}).length, color: '#d97706' },
            { label: 'عيادة', value: dayAppts.filter(function(a){return a.status==='in_clinic';}).length, color: '#0e7490' },
            { label: 'مكتمل', value: dayAppts.filter(function(a){return a.status==='done';}).length, color: '#059669' }
          ]}
        ]
      });
      ChartKit.mount('analytics-quote', {
        stats: [
          { icon: '🧾', label: 'عروض', value: quotations.length, bg: 'rgba(5,150,105,0.1)' },
          { icon: '💰', label: 'إجمالي', value: Math.round(quotations.reduce(function(s,q){return s+q.total;},0)/1000)+'K', color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '✅', label: 'معتمد', value: quotations.filter(function(q){return q.status==='approved';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏳', label: 'بانتظار', value: quotations.filter(function(q){return q.status==='pending';}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'قيمة العروض (K)', color: '#059669', items: quotations.map(function(q) {
            return { label: q.id.replace('QT-2026-',''), value: Math.round(q.total/1000), display: Math.round(q.total/1000)+'K' };
          })},
          { type: 'donut', title: 'حالة العروض', items: [
            { label: 'معتمد', value: quotations.filter(function(q){return q.status==='approved';}).length, color: '#059669' },
            { label: 'بانتظار', value: quotations.filter(function(q){return q.status==='pending';}).length, color: '#d97706' }
          ]}
        ]
      });
      ChartKit.mount('analytics-patients', {
        stats: [
          { icon: '👤', label: 'مرضى', value: patientsRegistry.length, bg: 'rgba(124,58,237,0.1)' },
          { icon: '✅', label: 'نشط', value: patientsRegistry.filter(function(p){return p.status==='active';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '💰', label: 'عرض سعر', value: patientsRegistry.filter(function(p){return p.status==='quoted';}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '⏸️', label: 'غير نشط', value: patientsRegistry.filter(function(p){return p.status==='inactive';}).length, bg: 'rgba(100,116,139,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'حالة المريض', items: [
            { label: 'نشط', value: patientsRegistry.filter(function(p){return p.status==='active';}).length, color: '#059669' },
            { label: 'عرض سعر', value: patientsRegistry.filter(function(p){return p.status==='quoted';}).length, color: '#d97706' },
            { label: 'مكتمل', value: patientsRegistry.filter(function(p){return p.status==='done';}).length, color: '#0e7490' },
            { label: 'غير نشط', value: patientsRegistry.filter(function(p){return p.status==='inactive';}).length, color: '#64748b' }
          ]},
          { type: 'bar', title: 'جهات التعاقد', color: '#7c3aed', items: [
            { label: 'التأمين الوطني', value: 3 }, { label: 'التأمين الصحي', value: 2 }, { label: 'ذوي الإعاقة', value: 2 }
          ]}
        ]
      });
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
      renderCalendar();
    }

    function toggleAddPatientForm(forceOpen) {
      var wrap = document.getElementById('addPatientFormWrap');
      var section = document.getElementById('addPatientSection');
      var btn = document.getElementById('btnAddPatient');
      var isOpen = wrap.classList.contains('open');
      var open = forceOpen === true ? true : (forceOpen === false ? false : !isOpen);
      wrap.classList.toggle('open', open);
      section.classList.toggle('expanded', open);
      if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function() {
          var nameEl = document.getElementById('newPatientName');
          if (nameEl) nameEl.focus();
        }, 350);
      }
    }

    function closeAddPatientForm() {
      toggleAddPatientForm(false);
    }

    function openAddPatientForm() {
      toggleAddPatientForm(true);
    }

    document.getElementById('closeQuoteModal').addEventListener('click', closeQuoteModal);
    document.getElementById('btnCloseQuoteModal').addEventListener('click', closeQuoteModal);
    document.getElementById('quoteModal').addEventListener('click', function(e) {
      if (e.target === document.getElementById('quoteModal')) closeQuoteModal();
    });

    document.getElementById('closePatientFileModal').addEventListener('click', closePatientFileModal);
    document.getElementById('btnClosePatientFile').addEventListener('click', closePatientFileModal);
    document.getElementById('patientFileModal').addEventListener('click', function(e) {
      if (e.target === document.getElementById('patientFileModal')) closePatientFileModal();
    });

    document.getElementById('btnAddPatient').addEventListener('click', function() {
      toggleAddPatientForm();
    });

    document.getElementById('btnCancelAddPatient').addEventListener('click', closeAddPatientForm);

    var patientTypeSel = document.getElementById('newPatientType');
    if (patientTypeSel) {
      patientTypeSel.addEventListener('change', function() {
        var isMil = patientTypeSel.value === 'military';
        document.getElementById('grpRank').style.display = isMil ? '' : 'none';
      });
    }

    function genPatientId(type) {
      var prefix = type === 'military' ? 'MIL' : 'CIV';
      var seq = String(Date.now()).slice(-4);
      return 'PT-' + prefix + '-' + seq;
    }

    function showPatientCard(data) {
      var meta = CasesWorkflow.getPatientTypeMeta(data.patientType);
      document.getElementById('picType').textContent = meta.icon + ' ' + meta.label;
      document.getElementById('picName').textContent = data.name;
      document.getElementById('picId').textContent = data.patientId;
      document.getElementById('picCompany').textContent = data.company;
      var rankEl = document.getElementById('picRank');
      if (data.patientType === 'military' && data.rank) {
        rankEl.style.display = '';
        rankEl.textContent = 'الرتبة: ' + data.rank;
      } else {
        rankEl.style.display = 'none';
      }
      document.getElementById('picQrText').textContent = data.patientQr;
      var card = document.getElementById('patientIdCard');
      if (card) card.setAttribute('data-type', data.patientType);
      document.getElementById('patientCardModal').classList.add('visible');
    }

    function closePatientCard() {
      document.getElementById('patientCardModal').classList.remove('visible');
    }
    var btnClosePC = document.getElementById('btnClosePatientCard');
    var btnClosePCx = document.getElementById('closePatientCardModal');
    if (btnClosePC) btnClosePC.addEventListener('click', closePatientCard);
    if (btnClosePCx) btnClosePCx.addEventListener('click', closePatientCard);

    document.getElementById('btnSavePatient').addEventListener('click', function() {
      var name = document.getElementById('newPatientName').value.trim();
      var phone = document.getElementById('newPhone').value.trim();
      var company = document.getElementById('newCompany').value;
      var patientType = document.getElementById('newPatientType').value || 'civilian';
      var rank = document.getElementById('newRank') ? document.getElementById('newRank').value.trim() : '';
      if (!name || !phone || !company) {
        alert('يرجى تعبئة جميع الحقول');
        return;
      }
      if (!/^01[0-9]{9}$/.test(phone)) {
        alert('رقم الهاتف يجب أن يكون 11 رقم ويبدأ بـ 01');
        return;
      }
      var patientId = genPatientId(patientType);
      var patientQr = 'QR-' + patientId;
      appointments.unshift({
        time: 'جديد',
        date: calendarView.selectedDate,
        visitType: 'exam',
        name: name,
        phone: phone,
        company: company,
        patientType: patientType,
        status: 'waiting',
        statusLabel: 'انتظار'
      });
      patientsRegistry.unshift({
        name: name,
        phone: phone,
        company: company,
        patientType: patientType,
        patientId: patientId,
        rank: rank,
        registered: calendarView.selectedDate,
        lastVisit: calendarView.selectedDate,
        status: 'active',
        statusLabel: 'نشط'
      });
      closeAddPatientForm();
      renderCalendar();
      renderReceptionAnalytics();
      renderAppointments();
      renderPatients();
      showToast('تم تسجيل ' + name + ' — Patient ID: ' + patientId);
      showPatientCard({ name: name, company: company, patientType: patientType, rank: rank, patientId: patientId, patientQr: patientQr });
      document.getElementById('newPatientName').value = '';
      document.getElementById('newPhone').value = '';
      document.getElementById('newCompany').value = '';
      if (document.getElementById('newRank')) document.getElementById('newRank').value = '';
    });

    document.getElementById('btnScanQR').addEventListener('click', function() { openQRScan(); });
    document.getElementById('btnSimulateReturn').addEventListener('click', function() { openQRScan('QT-2026-0847'); });
    document.getElementById('closeQrModal').addEventListener('click', closeQRScanModal);
    document.getElementById('qrModal').addEventListener('click', function(e) {
      if (e.target === document.getElementById('qrModal')) closeQRScanModal();
    });

    document.getElementById('calPrev').addEventListener('click', function() { changeCalendarMonth(-1); });
    document.getElementById('calNext').addEventListener('click', function() { changeCalendarMonth(1); });
    document.getElementById('calToday').addEventListener('click', function() { selectCalendarDate(TODAY_DATE); });

    var apptSearchEl = document.getElementById('apptSearch');
    if (apptSearchEl) apptSearchEl.addEventListener('input', function() { renderAppointments(); });
    var apptStatusEl = document.getElementById('apptStatusFilter');
    if (apptStatusEl) apptStatusEl.addEventListener('change', function() { renderAppointments(); });
    var patientStatusEl = document.getElementById('patientStatusFilter');
    if (patientStatusEl) patientStatusEl.addEventListener('change', function() {
      renderPatients((document.getElementById('patientSearch') || {}).value.trim());
    });

    document.getElementById('patientSearch').addEventListener('input', function(e) {
      renderPatients(e.target.value.trim());
    });

    var uploadZone = document.getElementById('uploadZone');
    var fileInput = document.getElementById('fileInput');

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

    function simulateOCR() {
      document.getElementById('ocrLoading').classList.add('visible');
      document.getElementById('ocrResults').classList.remove('visible');
      document.getElementById('ocrForm').style.display = 'none';
      document.getElementById('ocrActions').style.display = 'none';
      uploadZone.style.display = 'none';

      setTimeout(function() {
        document.getElementById('ocrLoading').classList.remove('visible');

        var mockData = {
          name: 'يوسف عمر محسن',
          amount: '95,000 ج.م',
          company: 'مجلس الدفاع المدني',
          ref: 'REF-2026-DM-4521',
          date: '05/06/2026'
        };

        document.getElementById('ocrName').textContent = mockData.name;
        document.getElementById('ocrAmount').textContent = mockData.amount;
        document.getElementById('ocrCompany').textContent = mockData.company;
        document.getElementById('ocrRef').textContent = mockData.ref;
        document.getElementById('ocrDate').textContent = mockData.date;

        document.getElementById('confirmName').value = mockData.name;
        document.getElementById('confirmAmount').value = '95000';

        document.getElementById('ocrResults').classList.add('visible');
        document.getElementById('ocrForm').style.display = 'grid';
        document.getElementById('ocrActions').style.display = 'flex';
      }, 2200);
    }

    document.getElementById('btnBypass').addEventListener('click', function() {
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

    document.getElementById('btnResetOcr').addEventListener('click', resetOCR);

    function resetOCR() {
      uploadZone.style.display = 'block';
      document.getElementById('ocrResults').classList.remove('visible');
      document.getElementById('ocrForm').style.display = 'none';
      document.getElementById('ocrActions').style.display = 'none';
      fileInput.value = '';
    }

    syncPricingQueueToQuotes();
    renderReceptionAnalytics();
    renderCalendar();
    renderAppointments();
    renderPatients();
    renderQuoteTable();
    renderDeliveryTable();

    document.getElementById('quoteSearch').addEventListener('input', function(e) {
      quoteSearchTerm = e.target.value.trim();
      renderQuoteTable();
    });
