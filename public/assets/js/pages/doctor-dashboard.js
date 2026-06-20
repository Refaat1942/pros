    var activePage = document.body.dataset.activePage || '';

    var recommendationsSelect = null;
    if (document.getElementById('medicalRecommendationsSelect')) {
      recommendationsSelect = StockMultiSelect.create('medicalRecommendationsSelect');
    }

    window.addEventListener('storage', function(e) {
      if (e.key === StockCatalog.STORAGE_KEY && recommendationsSelect) {
        recommendationsSelect.refresh();
      }
    });

    var queue = [];
    var medicalRecords = [];
    var transferred = [];

    var PT_META = {
      civilian: { label: 'مدني', icon: '🌐', badge: 'civilian' },
      military: { label: 'عسكري', icon: '🪖', badge: 'military' }
    };
    function ptMeta(t) { return PT_META[t] || PT_META.civilian; }

    function normalizeRec(item) {
      if (typeof item === 'string') return { name: item, qty: 1 };
      return {
        name: item.name,
        qty: item.qty || item.selectedQty || 1,
        code: item.code || item.stock_item_code || ''
      };
    }

    function resolveRecCode(name, code) {
      if (code) return code;
      var match = StockCatalog.getAll().find(function(i) { return i.name === name; });
      return match ? match.code : '—';
    }

    function formatRecommendations(items) {
      if (!items || !items.length) return '—';
      return items.map(function(item) {
        var n = normalizeRec(item);
        var label = n.qty > 1 ? n.name + ' × ' + n.qty : n.name;
        return '<span>' + label + '</span>';
      }).join('');
    }

    function recommendationsText(items) {
      if (!items || !items.length) return '—';
      return items.map(function(item) {
        var n = normalizeRec(item);
        return n.qty > 1 ? n.name + ' (' + n.qty + ')' : n.name;
      }).join('، ');
    }

    function escHtml(str) {
      return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getRecordStatus(record) {
      return record.status || 'معتمد';
    }

    function formatRecordStatusBadge(record, idx) {
      var status = getRecordStatus(record);
      if (status === 'تم التحويل للمخزون') {
        return '<span class="record-status-done">✅ تم التحويل للمخزون</span>';
      }
      if (status === 'تحويل للمخزون' || status === 'معتمد') {
        return '<button type="button" class="btn-record-transfer" data-record-idx="' + idx + '">📦 تحويل للمخزون</button>';
      }
      return '<span class="record-locked">🔒 ' + escHtml(status) + '</span>';
    }

    function formatRecordStatusMeta(record) {
      var status = getRecordStatus(record);
      if (status === 'تم التحويل للمخزون') {
        return '<span class="record-status-done">✅ تم التحويل للمخزون — غير قابل للتعديل</span>';
      }
      if (status === 'تحويل للمخزون' || status === 'معتمد') {
        return '<span class="record-status-transfer">📦 في انتظار التحويل للمخزون — غير قابل للتعديل</span>';
      }
      return '<span class="record-locked">🔒 ' + escHtml(status) + ' — غير قابل للتعديل</span>';
    }

    function transferRecordToInventory(recordIdx) {
      var record = medicalRecords[recordIdx];
      if (!record) return;
      if (getRecordStatus(record) === 'تم التحويل للمخزون') return;

      transferred.unshift({
        name: record.name,
        recommendations: record.recommendations,
        company: record.company || '—',
        date: record.date,
        status: 'قيد التوصيف'
      });
      record.status = 'تم التحويل للمخزون';
      renderRecords();
      renderTransferred();
      renderDoctorAnalytics();
      showToast('تم تحويل ' + record.name + ' إلى المخزون');
    }

    function buildRecommendationRows(recommendations) {
      return (recommendations || []).map(function(item) {
        var n = normalizeRec(item);
        return '<tr>' +
          '<td><strong>' + escHtml(n.name) + '</strong></td>' +
          '<td>' + escHtml(resolveRecCode(n.name, n.code)) + '</td>' +
          '<td>' + n.qty + '</td>' +
          '</tr>';
      }).join('');
    }

    function formatTransferStatusMeta(status, statusGroup) {
      if (statusGroup === 'مكتمل' || status === 'مكتمل') return '<span class="record-status-done">✅ مكتمل</span>';
      if (statusGroup === 'في الورشة' || (status && status.indexOf('التصنيع') !== -1)) {
        return '<span class="record-status-transfer" style="color:var(--primary)">🏭 في الورشة</span>';
      }
      return '<span class="record-status-transfer">⚙️ قيد التوصيف</span>';
    }

    function openTransferModal(transferIdx) {
      var item = transferred[transferIdx];
      if (!item) return;

      var linkedRecord = medicalRecords.find(function(r) { return r.name === item.name; });
      var diagnosis = item.diagnosis || (linkedRecord && linkedRecord.diagnosis) || '';
      var prescription = item.prescription || (linkedRecord && linkedRecord.prescription) || '';

      document.getElementById('recordModalTitle').textContent = item.name;
      document.getElementById('recordModalMeta').innerHTML = formatTransferStatusMeta(item.status, item.statusGroup);

      var recRows = buildRecommendationRows(item.recommendations);
      var diagnosisBlock = diagnosis
        ? '<div class="record-modal-section">' +
            '<h4>التشخيص الدقيق</h4>' +
            '<div class="record-diagnosis-text">' + escHtml(diagnosis) + '</div>' +
          '</div>'
        : '';
      var prescriptionBlock = prescription
        ? '<div class="record-modal-section">' +
            '<h4>الروشتة الطبية</h4>' +
            '<div class="record-diagnosis-text">' + escHtml(prescription) + '</div>' +
          '</div>'
        : '';

      document.getElementById('recordModalBody').innerHTML =
        '<div class="record-detail-grid">' +
          '<div class="record-detail-item"><div class="label">جهة التعاقد</div><div class="value">' + escHtml(item.company) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">تاريخ التحويل</div><div class="value">' + escHtml(item.date) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">الحالة</div><div class="value">' + escHtml(item.status) + '</div></div>' +
        '</div>' +
        diagnosisBlock +
        prescriptionBlock +
        '<div class="record-modal-section">' +
          '<h4>التوصيات الطبية</h4>' +
          '<table class="record-rec-table">' +
            '<thead><tr><th>الصنف</th><th>الكود</th><th>الكمية</th></tr></thead>' +
            '<tbody>' + (recRows || '<tr><td colspan="3">—</td></tr>') + '</tbody>' +
          '</table>' +
        '</div>';

      document.getElementById('recordDetailModal').classList.add('open');
    }

    function formatRecordViewButton(recordIdx) {
      return '<button type="button" class="btn btn-secondary btn-record-view" style="padding:6px 12px;font-size:12px;" data-record-idx="' + recordIdx + '">عرض</button>';
    }

    function openRecordModal(recordIdx) {
      var record = medicalRecords[recordIdx];
      if (!record) return;

      var tm = ptMeta(record.patientType);
      document.getElementById('recordModalTitle').textContent = record.name;
      document.getElementById('recordModalMeta').innerHTML =
        '<span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span> · ' + escHtml(record.date);

      document.getElementById('recordModalBody').innerHTML =
        '<div class="record-detail-grid">' +
          '<div class="record-detail-item"><div class="label">رقم الهاتف</div><div class="value" style="direction:ltr;text-align:right;">' + escHtml(record.phone || '—') + '</div></div>' +
          '<div class="record-detail-item"><div class="label">الرقم القومي</div><div class="value" style="direction:ltr;text-align:right;">' + escHtml(record.nationalId || '—') + '</div></div>' +
          '<div class="record-detail-item"><div class="label">جهة التعاقد</div><div class="value">' + escHtml(record.company || '—') + '</div></div>' +
          '<div class="record-detail-item"><div class="label">الطبيب المعالج</div><div class="value">' + escHtml(record.doctor) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">تاريخ التقرير</div><div class="value">' + escHtml(record.date) + '</div></div>' +
        '</div>' +
        '<div class="record-modal-section">' +
          '<h4>التشخيص الدقيق</h4>' +
          '<div class="record-diagnosis-text">' + escHtml(record.diagnosis || '—') + '</div>' +
        '</div>' +
        '<div class="record-modal-section">' +
          '<h4>الروشتة الطبية</h4>' +
          '<div class="record-diagnosis-text">' + escHtml(record.prescription || '—') + '</div>' +
        '</div>';

      document.getElementById('recordDetailModal').classList.add('open');
    }

    function closeRecordModal() {
      var modal = document.getElementById('recordDetailModal');
      if (modal) modal.classList.remove('open');
    }

    bindIfPresent('recordModalClose', 'click', closeRecordModal);
    bindIfPresent('recordModalCloseBtn', 'click', closeRecordModal);
    bindIfPresent('recordDetailModal', 'click', closeRecordModal);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeRecordModal();
    });

    function getCsrfToken() {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    function bindIfPresent(id, event, handler) {
      var el = document.getElementById(id);
      if (el) el.addEventListener(event, handler);
    }

    var sectionTitles = {
      queue: 'العيادة الطبية — قائمة الانتظار',
      diagnosis: 'التشخيص الطبي — إدخال التقرير',
      records: 'السجل الطبي — التقارير المعتمدة',
      transfer: 'الحالات المحولة للمخزون'
    };

    function dashboardPageUrl(page) {
      var seg = window.location.pathname.split('/').filter(Boolean);
      return '/' + (seg[0] || 'doctor') + '/' + page;
    }

    function switchSection(sectionId) {
      if (!document.getElementById('section-' + sectionId)) {
        window.location.href = dashboardPageUrl(sectionId);
        return;
      }
      if (sectionId === 'diagnosis' && recommendationsSelect) {
        recommendationsSelect.refresh();
      }
      if (sectionId === 'diagnosis' && selectedPatient) {
        document.getElementById('saveBtn').disabled = false;
        document.getElementById('transferBtn').disabled = false;
      }
    }

    document.querySelectorAll('.nav-menu a[data-section]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        var sectionId = link.getAttribute('data-section');
        if (sectionId && !document.getElementById('section-' + sectionId)) {
          e.preventDefault();
          switchSection(sectionId);
        }
      });
    });

    function getFilteredQueue() {
      var search = (document.getElementById('queueSearch') || {}).value || '';
      search = search.trim();
      return queue.filter(function(p) {
        return !search || p.name.indexOf(search) !== -1 || p.company.indexOf(search) !== -1;
      });
    }

    function formatRecordSummary(record) {
      if (record.recommendations && record.recommendations.length) {
        return formatRecommendations(record.recommendations);
      }
      if (record.diagnosis) {
        var d = record.diagnosis.trim();
        return '<span>' + escHtml(d.length > 80 ? d.slice(0, 80) + '…' : d) + '</span>';
      }
      return '—';
    }

    function displayDateFromIso(iso) {
      if (!iso) return '—';
      var parts = String(iso).split('T')[0].split('-');
      if (parts.length !== 3) return String(iso);
      return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function mapRecordFromApi(row) {
      var items = (row.items || []).map(function(item) {
        return {
          name: item.name,
          code: item.stock_item_code,
          qty: item.qty || 1
        };
      });

      return {
        id: row.id,
        name: row.patient_name || '—',
        phone: row.phone || '—',
        nationalId: row.national_id || '',
        company: row.company_name || '—',
        patientType: row.patient_type || 'civilian',
        diagnosis: row.diagnosis || '',
        prescription: row.prescription || '',
        recommendations: items,
        doctor: row.doctor_name || '—',
        date: displayDateFromIso(row.record_date),
        status: row.status || 'معتمد',
        locked: !!row.locked
      };
    }

    function fetchRecordsFromServer(callback) {
      return fetch('/doctor/records/list', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function(res) {
          if (!res.ok) throw new Error('records list failed');
          return res.json();
        })
        .then(function(payload) {
          medicalRecords = (payload.data || []).map(mapRecordFromApi);
          window.__MEDICAL_RECORDS = payload.data || [];
          if (callback) callback();
        })
        .catch(function(err) {
          console.error('fetchRecordsFromServer', err);
          if (callback) callback();
        });
    }

    function loadRecords() {
      if (Array.isArray(window.__MEDICAL_RECORDS)) {
        medicalRecords = window.__MEDICAL_RECORDS.map(mapRecordFromApi);
        renderRecords();
      }

      fetchRecordsFromServer(function () {
        renderRecords();
      });
    }

    function getFilteredRecords() {
      var search = (document.getElementById('recordsSearch') || {}).value || '';
      search = search.trim();
      return medicalRecords.filter(function(r) {
        var recText = recommendationsText(r.recommendations);
        return !search
          || r.name.indexOf(search) !== -1
          || (r.phone && r.phone.indexOf(search) !== -1)
          || recText.indexOf(search) !== -1
          || (r.diagnosis && r.diagnosis.indexOf(search) !== -1);
      });
    }

    function getFilteredTransferred() {
      var search = (document.getElementById('transferSearch') || {}).value || '';
      var status = (document.getElementById('transferStatusFilter') || {}).value || 'all';
      search = search.trim();
      return transferred.filter(function(t) {
        var ms = !search || t.name.indexOf(search) !== -1 || t.company.indexOf(search) !== -1;
        var mst = status === 'all' || t.statusGroup === status;
        return ms && mst;
      });
    }

    function exportQueue(type) {
      var data = getFilteredQueue();
      var headers = ['#', 'اسم المريض', 'الجهة', 'تاريخ الإضافة'];
      var rows = data.map(function(p, i) {
        return [i + 1, p.name, p.company, p.queuedAt || p.wait || '—'];
      });
      if (type === 'excel') ExportKit.toExcel('قائمة_الانتظار', headers, rows);
      else ExportKit.toPDF('قائمة الانتظار الرقمية', headers, rows);
    }

    function bindRecordViewButtons(root) {
      root = root || document.getElementById('recordsTable');
      if (!root) return;
      root.querySelectorAll('.btn-record-view').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          var idx = parseInt(btn.getAttribute('data-record-idx'), 10);
          if (!isNaN(idx) && idx >= 0) {
            openRecordModal(idx);
            return;
          }
          var id = parseInt(btn.getAttribute('data-record-id'), 10);
          var found = medicalRecords.findIndex(function(r) { return r.id === id; });
          if (found !== -1) openRecordModal(found);
        });
      });
    }

    function exportRecords(type) {
      var data = getFilteredRecords();
      var headers = ['المريض', 'رقم الهاتف', 'التشخيص', 'الروشتة', 'الطبيب', 'التاريخ'];
      var rows = data.map(function(r) {
        return [r.name, r.phone || '—', r.diagnosis || '—', r.prescription || '—', r.doctor, r.date];
      });
      if (type === 'excel') ExportKit.toExcel('السجل_الطبي', headers, rows);
      else ExportKit.toPDF('السجل الطبي — التقارير المعتمدة', headers, rows);
    }

    function exportTransferred(type) {
      var data = getFilteredTransferred();
      var headers = ['المريض', 'التوصيات الطبية', 'الجهة', 'تاريخ التحويل', 'الحالة'];
      var rows = data.map(function(t) {
        return [t.name, recommendationsText(t.recommendations), t.company, t.date, t.status];
      });
      if (type === 'excel') ExportKit.toExcel('المحولون_للمخزون', headers, rows);
      else ExportKit.toPDF('الحالات المحولة للمخزون', headers, rows);
    }

    function renderRecords() {
      var table = document.getElementById('recordsTable');
      if (!table) return;
      var filtered = getFilteredRecords();
      table.innerHTML = filtered.length
        ? filtered.map(function(r) {
        var idx = medicalRecords.indexOf(r);
        return '<tr data-record-id="' + (r.id || '') + '">' +
          '<td><strong>' + escHtml(r.name) + '</strong></td>' +
          '<td style="font-size:12px;color:var(--text-muted);direction:ltr;text-align:right;">' + escHtml(r.phone || '—') + '</td>' +
          '<td><div class="rec-list">' + formatRecordSummary(r) + '</div></td>' +
          '<td>' + escHtml(r.doctor) + '</td>' +
          '<td>' + escHtml(r.date) + '</td>' +
          '<td>' + formatRecordViewButton(idx) + '</td>' +
          '</tr>';
      }).join('')
        : '<tr class="pagination-empty-row"><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد تقارير معتمدة بعد — سيظهر المريض هنا بعد حفظ واعتماد التشخيص.</td></tr>';

      bindRecordViewButtons(table);

      var rc = document.getElementById('recordsCount');
      if (rc) rc.textContent = filtered.length + ' تقرير';
      var rhc = document.getElementById('recordsHeaderCount');
      if (rhc) rhc.textContent = filtered.length + ' تقرير';
      if (window.TablePagination) TablePagination.refreshById('recordsTable');
    }

    function initServerRecordsRows() {
      bindRecordViewButtons(document.getElementById('recordsTable'));
    }

    function mapTransferFromApi(row) {
      return {
        id: row.id,
        caseNo: row.case_no || '',
        name: row.name || '—',
        company: row.company || '—',
        patientType: row.patient_type || 'civilian',
        date: row.date || '—',
        status: row.status || '—',
        statusGroup: row.status_group || 'قيد التوصيف',
        diagnosis: row.diagnosis || '',
        prescription: row.prescription || '',
        diagnosis: row.diagnosis || '',
        prescription: row.prescription || '',
        recommendations: (row.recommendations || []).map(function(item) {
          return { name: item.name, code: item.code, qty: item.qty || 1 };
        })
      };
    }

    function fetchTransfersFromServer(callback) {
      return fetch('/doctor/transfer/list', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function(res) {
          if (!res.ok) throw new Error('transfer list failed');
          return res.json();
        })
        .then(function(payload) {
          transferred = (payload.data || []).map(mapTransferFromApi);
          window.__TRANSFERRED_CASES = payload.data || [];
          if (callback) callback();
        })
        .catch(function(err) {
          console.error('fetchTransfersFromServer', err);
          if (callback) callback();
        });
    }

    function updateTransferAnalytics() {
      var root = document.getElementById('analytics-transfer');
      if (!root) return;
      var values = root.querySelectorAll('.ck-stat-value');
      var total = transferred.length;
      var spec = transferred.filter(function(t) { return t.statusGroup === 'قيد التوصيف'; }).length;
      var workshop = transferred.filter(function(t) { return t.statusGroup === 'في الورشة'; }).length;
      var done = transferred.filter(function(t) { return t.statusGroup === 'مكتمل'; }).length;
      if (values[0]) values[0].textContent = String(total);
      if (values[1]) values[1].textContent = String(spec);
      if (values[2]) values[2].textContent = String(workshop);
      if (values[3]) values[3].textContent = String(done);
    }

    function loadTransfers() {
      if (Array.isArray(window.__TRANSFERRED_CASES)) {
        transferred = window.__TRANSFERRED_CASES.map(mapTransferFromApi);
        renderTransferred();
        updateTransferAnalytics();
      }

      fetchTransfersFromServer(function() {
        renderTransferred();
        updateTransferAnalytics();
      });
    }

    function initServerTransferRows() {
      var tbody = document.getElementById('transferredTable');
      if (!tbody || tbody.dataset.serverRendered !== '1') return;

      tbody.querySelectorAll('tr.record-row-clickable[data-transfer-id]').forEach(function(row) {
        row.addEventListener('click', function() {
          var id = parseInt(row.getAttribute('data-transfer-id'), 10);
          var idx = transferred.findIndex(function(t) { return t.id === id; });
          if (idx !== -1) openTransferModal(idx);
        });
      });
    }

    function renderTransferred() {
      var table = document.getElementById('transferredTable');
      if (!table) return;
      var filtered = getFilteredTransferred();
      table.innerHTML = filtered.length
        ? filtered.map(function(t) {
        var idx = transferred.indexOf(t);
        return '<tr class="record-row-clickable" data-transfer-idx="' + idx + '" data-transfer-id="' + (t.id || '') + '" title="عرض التفاصيل">' +
          '<td><strong>' + escHtml(t.name) + '</strong></td>' +
          '<td><div class="rec-list">' + formatTransferSummary(t) + '</div></td>' +
          '<td>' + escHtml(t.company) + '</td>' +
          '<td>' + escHtml(t.date) + '</td>' +
          '<td><span class="priority-badge normal">' + escHtml(t.status) + '</span></td>' +
          '</tr>';
      }).join('')
        : '<tr class="pagination-empty-row"><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد حالات محوّلة بعد — تظهر هنا بعد اعتماد التشخيص وتحويل الحالة للتوصيف الفني.</td></tr>';

      table.querySelectorAll('tr[data-transfer-idx]').forEach(function(row) {
        row.addEventListener('click', function() {
          openTransferModal(parseInt(row.getAttribute('data-transfer-idx'), 10));
        });
      });

      var transferredCount = document.getElementById('transferredCount');
      if (transferredCount) transferredCount.textContent = filtered.length;
      var tc = document.getElementById('transferCount');
      if (tc) tc.textContent = filtered.length + ' حالة';
      if (window.TablePagination) TablePagination.refreshById('transferredTable');
      updateTransferAnalytics();
    }

    function formatTransferSummary(item) {
      if (item.recommendations && item.recommendations.length) {
        return formatRecommendations(item.recommendations);
      }
      return '—';
    }

    function initServerQueueRows() {
      var tbody = document.getElementById('queueTable');
      if (!tbody || tbody.dataset.serverRendered !== '1') return;

      tbody.querySelectorAll('tr.queue-row-clickable[data-href]').forEach(function(row) {
        row.addEventListener('click', function() {
          window.location.href = row.getAttribute('data-href');
        });
      });

      var searchInput = document.getElementById('queueSearch');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          var q = searchInput.value.trim().toLowerCase();
          var visible = 0;
          tbody.querySelectorAll('tr.queue-row-clickable').forEach(function(row) {
            var hay = (row.dataset.search || row.textContent || '').toLowerCase();
            var show = !q || hay.indexOf(q) !== -1;
            if (show) {
              delete row.dataset.paginationSkip;
              visible++;
            } else {
              row.dataset.paginationSkip = '1';
            }
          });
          var badge = document.getElementById('queueBadge');
          var count = document.getElementById('queueCount');
          if (badge) badge.textContent = visible;
          if (count) count.textContent = visible + ' مريض';
          if (window.TablePagination) TablePagination.refreshById('queueTable');
        });
      }
    }

    var selectedPatient = null;

    function renderQueue() {
      var tbody = document.getElementById('queueTable');
      if (!tbody || tbody.dataset.serverRendered === '1') return;
      var filtered = getFilteredQueue();
      tbody.innerHTML = filtered.map(function(p, i) {
        var selected = selectedPatient && selectedPatient.id === p.id ? 'selected' : '';
        var tm = ptMeta(p.patientType);
        return '<tr class="' + selected + '" data-id="' + p.id + '">' +
          '<td><span class="queue-num">' + (i + 1) + '</span></td>' +
          '<td><strong>' + p.name + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
          '<td>' + p.company + '</td>' +
          '<td><span class="wait-time">' + p.wait + '</span></td>' +
          '</tr>';
      }).join('');

      tbody.querySelectorAll('tr').forEach(function(row) {
        row.addEventListener('click', function() {
          selectPatient(parseInt(row.getAttribute('data-id')));
        });
      });
      var queueBadge = document.getElementById('queueBadge');
      if (queueBadge) queueBadge.textContent = filtered.length;
      var waitingCount = document.getElementById('waitingCount');
      if (waitingCount) waitingCount.textContent = queue.length;
      var qc = document.getElementById('queueCount');
      if (qc) qc.textContent = filtered.length + ' مريض';
      if (window.TablePagination) TablePagination.refreshById('queueTable');
    }

    ['queueSearch'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderQueue); el.addEventListener('change', renderQueue); }
    });
    ['recordsSearch'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderRecords); el.addEventListener('change', renderRecords); }
    });
    ['transferSearch','transferStatusFilter'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', renderTransferred); el.addEventListener('change', renderTransferred); }
    });

    function selectPatient(id) {
      selectedPatient = queue.find(function(p) { return p.id === id; });
      if (!selectedPatient) return;
      var tm = ptMeta(selectedPatient.patientType);
      var typeTxt = ' | التصنيف: ' + tm.icon + ' ' + tm.label + (selectedPatient.rank ? ' (' + selectedPatient.rank + ')' : '');
      var patientBar = document.getElementById('patientBar');
      if (patientBar) patientBar.classList.add('visible');
      var selectedPatientName = document.getElementById('selectedPatientName');
      var selectedPatientInfo = document.getElementById('selectedPatientInfo');
      if (selectedPatientName) selectedPatientName.textContent = selectedPatient.name;
      if (selectedPatientInfo) selectedPatientInfo.textContent = 'الرقم القومي: ' + selectedPatient.nationalId + ' | جهة التعاقد: ' + selectedPatient.company + typeTxt;
      var silentNote = document.getElementById('silentClinicNote');
      if (silentNote) silentNote.style.display = selectedPatient.patientType === 'military' ? 'flex' : 'none';
      var saveBtn = document.getElementById('saveBtn');
      var transferBtn = document.getElementById('transferBtn');
      if (saveBtn) saveBtn.disabled = false;
      if (transferBtn) transferBtn.disabled = false;
      var diagnosisForm = document.getElementById('diagnosisForm');
      if (diagnosisForm) diagnosisForm.reset();
      if (recommendationsSelect) recommendationsSelect.reset();
      renderQueue();
    }

    function showToast(msg) {
      if (window.DashboardToast) {
        window.DashboardToast.show(msg);
        return;
      }
      var toast = document.getElementById('toast');
      if (!toast) return;
      toast.innerHTML = '✅ ' + msg;
      toast.classList.add('show');
      setTimeout(function() { toast.classList.remove('show'); }, 5000);
    }

    function submitDiagnosisToServer(form) {
      var diagnosisEl = document.getElementById('diagnosis');
      if (!diagnosisEl || !diagnosisEl.value.trim()) {
        alert('يرجى تعبئة التشخيص الدقيق');
        return;
      }

      var saveBtn = document.getElementById('saveBtn');
      if (saveBtn) saveBtn.disabled = true;

      var formData = new FormData(form);
      formData.set('lock', '1');

      if (recommendationsSelect) {
        var selectedRecs = recommendationsSelect.getSelected();
        selectedRecs.forEach(function(item, index) {
          formData.append('items[' + index + '][stock_item_code]', item.code || '');
          formData.append('items[' + index + '][name]', item.name || '');
          formData.append('items[' + index + '][qty]', String(item.selectedQty || 1));
        });
      }

      fetch(form.action, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: formData,
        credentials: 'same-origin'
      })
        .then(function(res) {
          if (!res.ok) {
            return res.json().then(function(body) { throw body; });
          }
          return res.json();
        })
        .then(function() {
          showToast('تم التحويل للتوصيف');
          setTimeout(function() {
            window.location.href = dashboardPageUrl('records');
          }, 700);
        })
        .catch(function(err) {
          if (saveBtn) saveBtn.disabled = false;
          var msg = 'تعذّر حفظ التقرير';
          if (err && err.message) msg = err.message;
          if (err && err.errors) {
            msg = Object.keys(err.errors).map(function(k) {
              return err.errors[k].join(' ');
            }).join('\n');
          }
          alert(msg);
        });
    }

    function initDiagnosisForm() {
      var form = document.getElementById('diagnosisForm');
      if (!form) return;

      form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (window.DashboardValidation && !DashboardValidation.validateForm(form)) return;

        if (activePage === 'diagnosis' || form.querySelector('input[name="patient_id"]')) {
          submitDiagnosisToServer(form);
          return;
        }

        if (!selectedPatient) return;

        var selectedRecs = recommendationsSelect ? recommendationsSelect.getSelected() : [];
        var diagnosis = document.getElementById('diagnosis').value;

        if (!selectedRecs.length || !diagnosis.trim()) {
          alert('يرجى اختيار توصية طبية واحدة على الأقل وتعبئة التشخيص');
          return;
        }

        var prescriptionEl = document.getElementById('prescription');
        medicalRecords.unshift({
          name: selectedPatient.name,
          nationalId: selectedPatient.nationalId,
          company: selectedPatient.company,
          patientType: selectedPatient.patientType,
          diagnosis: diagnosis.trim(),
          prescription: prescriptionEl ? prescriptionEl.value.trim() : '',
          recommendations: selectedRecs.map(function(item) {
            return { name: item.name, code: item.code, qty: item.selectedQty || 1 };
          }),
          doctor: 'د. سارة عبدالله',
          date: '08/06/2026',
          status: 'تحويل للمخزون',
          locked: true
        });
        renderRecords();
        showToast('تم حفظ التقرير — الحالة: تحويل للمخزون');
      });
    }

    initDiagnosisForm();

    bindIfPresent('transferBtn', 'click', function() {
      if (!selectedPatient) {
        alert('يرجى اختيار مريض من قائمة الانتظار أولاً');
        return;
      }

      var selectedRecs = recommendationsSelect ? recommendationsSelect.getSelected() : [];
      if (!selectedRecs.length) {
        alert('يرجى اختيار صنف واحد على الأقل من التوصيات الطبية قبل التحويل');
        return;
      }

      showToast('تم تحويل ' + selectedPatient.name + ' إلى المخزون لصرف الأصناف');
      transferred.unshift({
        name: selectedPatient.name,
        recommendations: selectedRecs.map(function(item) {
          return { name: item.name, code: item.code, qty: item.selectedQty || 1 };
        }),
        company: selectedPatient.company,
        date: '08/06/2026',
        status: 'قيد التوصيف'
      });
      document.getElementById('transferredCount').textContent = transferred.length;
      renderTransferred();
      queue = queue.filter(function(p) { return p.id !== selectedPatient.id; });
      selectedPatient = null;
      var patientBar = document.getElementById('patientBar');
      if (patientBar) patientBar.classList.remove('visible');
      var saveBtn = document.getElementById('saveBtn');
      var transferBtn = document.getElementById('transferBtn');
      if (saveBtn) saveBtn.disabled = true;
      if (transferBtn) transferBtn.disabled = true;
      var diagnosisForm = document.getElementById('diagnosisForm');
      if (diagnosisForm) diagnosisForm.reset();
      if (recommendationsSelect) recommendationsSelect.reset();
      document.getElementById('waitingCount').textContent = queue.length;
      document.getElementById('queueBadge').textContent = queue.length;
      renderQueue();
      switchSection('transfer');
    });

    function countRecommendedItems(source) {
      var counts = {};
      source.forEach(function(row) {
        (row.recommendations || []).forEach(function(rec) {
          var n = normalizeRec(rec);
          counts[n.name] = (counts[n.name] || 0) + n.qty;
        });
      });
      return Object.keys(counts).map(function(name) {
        return { label: name.length > 16 ? name.slice(0, 16) + '…' : name, value: counts[name] };
      }).sort(function(a, b) { return b.value - a.value; }).slice(0, 6);
    }

    function renderDoctorAnalytics() {
      return;
    }
    renderDoctorAnalytics();
    initServerQueueRows();
    initServerRecordsRows();
    initServerTransferRows();
    if (document.getElementById('queueTable')) renderQueue();
    if (document.getElementById('recordsTable')) loadRecords();
    if (document.getElementById('transferredTable')) loadTransfers();
