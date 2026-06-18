    var recommendationsSelect = StockMultiSelect.create('medicalRecommendationsSelect');

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
      return { name: item.name, qty: item.qty || item.selectedQty || 1, code: item.code };
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

    function formatTransferStatusMeta(status) {
      if (status === 'مكتمل') return '<span class="record-status-done">✅ مكتمل</span>';
      if (status.indexOf('ورشة') !== -1) return '<span class="record-status-transfer" style="color:var(--primary)">🏭 في الورشة</span>';
      return '<span class="record-status-transfer">⚙️ قيد التوصيف</span>';
    }

    function openTransferModal(transferIdx) {
      var item = transferred[transferIdx];
      if (!item) return;

      var linkedRecord = medicalRecords.find(function(r) { return r.name === item.name; });

      document.getElementById('recordModalTitle').textContent = item.name;
      document.getElementById('recordModalMeta').innerHTML = formatTransferStatusMeta(item.status);

      var recRows = buildRecommendationRows(item.recommendations);
      var diagnosisBlock = linkedRecord && linkedRecord.diagnosis
        ? '<div class="record-modal-section">' +
            '<h4>التشخيص الدقيق</h4>' +
            '<div class="record-diagnosis-text">' + escHtml(linkedRecord.diagnosis) + '</div>' +
          '</div>'
        : '';

      document.getElementById('recordModalBody').innerHTML =
        '<div class="record-detail-grid">' +
          '<div class="record-detail-item"><div class="label">جهة التعاقد</div><div class="value">' + escHtml(item.company) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">تاريخ التحويل</div><div class="value">' + escHtml(item.date) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">الحالة</div><div class="value">' + escHtml(item.status) + '</div></div>' +
        '</div>' +
        diagnosisBlock +
        '<div class="record-modal-section">' +
          '<h4>الأصناف المحولة</h4>' +
          '<table class="record-rec-table">' +
            '<thead><tr><th>الصنف</th><th>الكود</th><th>الكمية</th></tr></thead>' +
            '<tbody>' + (recRows || '<tr><td colspan="3">—</td></tr>') + '</tbody>' +
          '</table>' +
        '</div>';

      document.getElementById('recordDetailModal').classList.add('open');
    }

    function openRecordModal(recordIdx) {
      var record = medicalRecords[recordIdx];
      if (!record) return;

      document.getElementById('recordModalTitle').textContent = record.name;
      document.getElementById('recordModalMeta').innerHTML = formatRecordStatusMeta(record);

      var recRows = buildRecommendationRows(record.recommendations);

      document.getElementById('recordModalBody').innerHTML =
        '<div class="record-detail-grid">' +
          '<div class="record-detail-item"><div class="label">الطبيب</div><div class="value">' + escHtml(record.doctor) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">التاريخ</div><div class="value">' + escHtml(record.date) + '</div></div>' +
          '<div class="record-detail-item"><div class="label">جهة التعاقد</div><div class="value">' + escHtml(record.company || '—') + '</div></div>' +
        '</div>' +
        '<div class="record-modal-section">' +
          '<h4>التشخيص الدقيق</h4>' +
          '<div class="record-diagnosis-text">' + escHtml(record.diagnosis || '—') + '</div>' +
        '</div>' +
        '<div class="record-modal-section">' +
          '<h4>التوصيات الطبية</h4>' +
          '<table class="record-rec-table">' +
            '<thead><tr><th>الصنف</th><th>الكود</th><th>الكمية</th></tr></thead>' +
            '<tbody>' + (recRows || '<tr><td colspan="3">—</td></tr>') + '</tbody>' +
          '</table>' +
        '</div>';

      document.getElementById('recordDetailModal').classList.add('open');
    }

    function closeRecordModal() {
      document.getElementById('recordDetailModal').classList.remove('open');
    }

    document.getElementById('recordModalClose').addEventListener('click', closeRecordModal);
    document.getElementById('recordModalCloseBtn').addEventListener('click', closeRecordModal);
    document.getElementById('recordDetailModal').addEventListener('click', closeRecordModal);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeRecordModal();
    });

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
      var priority = (document.getElementById('queuePriorityFilter') || {}).value || 'all';
      search = search.trim();
      return queue.filter(function(p) {
        var ms = !search || p.name.indexOf(search) !== -1 || p.company.indexOf(search) !== -1;
        var mp = priority === 'all' || p.priority === priority;
        return ms && mp;
      });
    }

    function getFilteredRecords() {
      var search = (document.getElementById('recordsSearch') || {}).value || '';
      search = search.trim();
      return medicalRecords.filter(function(r) {
        var recText = recommendationsText(r.recommendations);
        return !search || r.name.indexOf(search) !== -1 || recText.indexOf(search) !== -1;
      });
    }

    function getFilteredTransferred() {
      var search = (document.getElementById('transferSearch') || {}).value || '';
      var status = (document.getElementById('transferStatusFilter') || {}).value || 'all';
      search = search.trim();
      return transferred.filter(function(t) {
        var ms = !search || t.name.indexOf(search) !== -1 || t.company.indexOf(search) !== -1;
        var mst = status === 'all' || t.status === status;
        return ms && mst;
      });
    }

    function exportQueue(type) {
      var data = getFilteredQueue();
      var headers = ['#', 'اسم المريض', 'الجهة', 'الأولوية', 'الانتظار'];
      var rows = data.map(function(p, i) {
        return [i + 1, p.name, p.company, p.priority === 'urgent' ? 'عاجل' : 'عادي', p.wait];
      });
      if (type === 'excel') ExportKit.toExcel('قائمة_الانتظار', headers, rows);
      else ExportKit.toPDF('قائمة الانتظار الرقمية', headers, rows);
    }

    function exportRecords(type) {
      var data = getFilteredRecords();
      var headers = ['المريض', 'التوصيات الطبية', 'الطبيب', 'التاريخ', 'الحالة'];
      var rows = data.map(function(r) {
        return [r.name, recommendationsText(r.recommendations), r.doctor, r.date, getRecordStatus(r)];
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
      var filtered = getFilteredRecords();
      document.getElementById('recordsTable').innerHTML = filtered.map(function(r) {
        var idx = medicalRecords.indexOf(r);
        return '<tr class="record-row-clickable" data-record-idx="' + idx + '" title="عرض التفاصيل">' +
          '<td><strong>' + r.name + '</strong></td>' +
          '<td><div class="rec-list">' + formatRecommendations(r.recommendations) + '</div></td>' +
          '<td>' + r.doctor + '</td>' +
          '<td>' + r.date + '</td>' +
          '<td>' + formatRecordStatusBadge(r, idx) + '</td>' +
          '</tr>';
      }).join('');

      document.getElementById('recordsTable').querySelectorAll('tr[data-record-idx]').forEach(function(row) {
        row.addEventListener('click', function(e) {
          if (e.target.closest('.btn-record-transfer')) return;
          openRecordModal(parseInt(row.getAttribute('data-record-idx'), 10));
        });
      });

      document.getElementById('recordsTable').querySelectorAll('.btn-record-transfer').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          transferRecordToInventory(parseInt(btn.getAttribute('data-record-idx'), 10));
        });
      });

      var rc = document.getElementById('recordsCount');
      if (rc) rc.textContent = filtered.length + ' تقارير';
    }

    function renderTransferred() {
      var filtered = getFilteredTransferred();
      document.getElementById('transferredTable').innerHTML = filtered.map(function(t) {
        var idx = transferred.indexOf(t);
        return '<tr class="record-row-clickable" data-transfer-idx="' + idx + '" title="عرض التفاصيل">' +
          '<td><strong>' + t.name + '</strong></td>' +
          '<td><div class="rec-list">' + formatRecommendations(t.recommendations) + '</div></td>' +
          '<td>' + t.company + '</td>' +
          '<td>' + t.date + '</td>' +
          '<td><span class="priority-badge normal">' + t.status + '</span></td>' +
          '</tr>';
      }).join('');

      document.getElementById('transferredTable').querySelectorAll('tr[data-transfer-idx]').forEach(function(row) {
        row.addEventListener('click', function() {
          openTransferModal(parseInt(row.getAttribute('data-transfer-idx'), 10));
        });
      });

      document.getElementById('transferredCount').textContent = filtered.length;
      var tc = document.getElementById('transferCount');
      if (tc) tc.textContent = filtered.length + ' حالة';
    }

    var selectedPatient = null;

    function renderQueue() {
      var filtered = getFilteredQueue();
      var tbody = document.getElementById('queueTable');
      tbody.innerHTML = filtered.map(function(p, i) {
        var selected = selectedPatient && selectedPatient.id === p.id ? 'selected' : '';
        var tm = ptMeta(p.patientType);
        return '<tr class="' + selected + '" data-id="' + p.id + '">' +
          '<td><span class="queue-num">' + (i + 1) + '</span></td>' +
          '<td><strong>' + p.name + '</strong> <span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
          '<td>' + p.company + '</td>' +
          '<td><span class="priority-badge ' + p.priority + '">' + (p.priority === 'urgent' ? 'عاجل' : 'عادي') + '</span></td>' +
          '<td><span class="wait-time">' + p.wait + '</span></td>' +
          '</tr>';
      }).join('');

      tbody.querySelectorAll('tr').forEach(function(row) {
        row.addEventListener('click', function() {
          selectPatient(parseInt(row.getAttribute('data-id')));
        });
      });
      document.getElementById('queueBadge').textContent = filtered.length;
      document.getElementById('waitingCount').textContent = queue.length;
      var qc = document.getElementById('queueCount');
      if (qc) qc.textContent = filtered.length + ' مريض';
    }

    ['queueSearch','queuePriorityFilter'].forEach(function(id) {
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
      var tm = ptMeta(selectedPatient.patientType);
      var typeTxt = ' | التصنيف: ' + tm.icon + ' ' + tm.label + (selectedPatient.rank ? ' (' + selectedPatient.rank + ')' : '');
      document.getElementById('patientBar').classList.add('visible');
      document.getElementById('patientBarQueue').classList.add('visible');
      document.getElementById('selectedPatientName').textContent = selectedPatient.name;
      document.getElementById('selectedPatientInfo').textContent = 'الرقم القومي: ' + selectedPatient.nationalId + ' | جهة التعاقد: ' + selectedPatient.company + typeTxt;
      document.getElementById('selectedPatientNameQueue').textContent = selectedPatient.name;
      document.getElementById('selectedPatientInfoQueue').textContent = 'الرقم القومي: ' + selectedPatient.nationalId + ' | جهة التعاقد: ' + selectedPatient.company + typeTxt;
      var silentNote = document.getElementById('silentClinicNote');
      if (silentNote) silentNote.style.display = selectedPatient.patientType === 'military' ? 'flex' : 'none';
      document.getElementById('saveBtn').disabled = false;
      document.getElementById('transferBtn').disabled = false;
      document.getElementById('goToDiagnosis').disabled = false;
      document.getElementById('diagnosisForm').reset();
      if (recommendationsSelect) recommendationsSelect.reset();
      renderQueue();
    }

    function showToast(msg) {
      var toast = document.getElementById('toast');
      toast.innerHTML = '✅ ' + msg;
      toast.classList.add('show');
      setTimeout(function() { toast.classList.remove('show'); }, 3500);
    }

    document.getElementById('diagnosisForm').addEventListener('submit', function(e) {
      e.preventDefault();
      if (!selectedPatient) return;

      var selectedRecs = recommendationsSelect.getSelected();
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

    document.getElementById('transferBtn').addEventListener('click', function() {
      if (!selectedPatient) {
        alert('يرجى اختيار مريض من قائمة الانتظار أولاً');
        return;
      }

      var selectedRecs = recommendationsSelect.getSelected();
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
      document.getElementById('patientBar').classList.remove('visible');
      document.getElementById('patientBarQueue').classList.remove('visible');
      document.getElementById('saveBtn').disabled = true;
      document.getElementById('transferBtn').disabled = true;
      document.getElementById('goToDiagnosis').disabled = true;
      document.getElementById('diagnosisForm').reset();
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
    renderQueue();
    renderRecords();
    renderTransferred();
