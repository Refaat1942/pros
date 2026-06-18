    StockCatalog.ensureSeeded();
    var recommendationsSelect = StockMultiSelect.create('medicalRecommendationsSelect');

    window.addEventListener('storage', function(e) {
      if (e.key === StockCatalog.STORAGE_KEY && recommendationsSelect) {
        recommendationsSelect.refresh();
      }
    });

    var queue = [
      { id: 1, name: 'محمود عبد الرحمن أحمد', company: 'التأمين الوطني', priority: 'normal', wait: '12 دقيقة', nationalId: '29805151234567', patientType: 'civilian' },
      { id: 2, name: 'فاطمة حسين محمد', company: 'هيئة التأمين الصحي', priority: 'urgent', wait: '25 دقيقة', nationalId: '28512019876543', patientType: 'civilian' },
      { id: 3, name: 'عبدالله سامي رشاد', company: 'صندوق ذوي الإعاقة', priority: 'normal', wait: '8 دقائق', nationalId: '30102001112233', patientType: 'civilian' },
      { id: 4, name: 'مريم خالد إبراهيم', company: 'شركة مصر للتأمين', priority: 'normal', wait: '18 دقيقة', nationalId: '29003034567890', patientType: 'civilian' },
      { id: 5, name: 'يوسف عمر محسن', company: 'إدارة القوات المسلحة الطبية', priority: 'urgent', wait: '32 دقيقة', nationalId: '27808015678901', patientType: 'military', rank: 'نقيب' }
    ];

    var PT_META = {
      civilian: { label: 'مدني', icon: '🌐', badge: 'civilian' },
      military: { label: 'عسكري', icon: '🪖', badge: 'military' }
    };
    function ptMeta(t) { return PT_META[t] || PT_META.civilian; }

    var medicalRecords = [
      { name: 'سارة أحمد فؤاد', nationalId: '29805151234567', company: 'التأمين الوطني', recommendations: ['ركبة هيدروليكية', 'قدم Carbon Spring', 'بطانة Silicone'], diagnosis: 'بتر فخذي أيمن — مرحلة cicatrization مكتملة. حالة الجلد جيدة ومناسبة للتجهيز.', doctor: 'د. سارة عبدالله', date: '07/06/2026', status: 'معتمد', locked: true },
      { name: 'هدى محمود سعيد', nationalId: '28512019876543', company: 'هيئة التأمين الصحي', recommendations: ['مفصل كوع', 'محول Pyramidal'], diagnosis: 'بتر ساعد أيسر — يحتاج مفصل كوع هيدروليكي مع محول مناسب.', doctor: 'د. سارة عبدالله', date: '06/06/2026', status: 'معتمد', locked: true },
      { name: 'أحمد فاروق نبيل', nationalId: '29003034567890', company: 'شركة مصر للتأمين', recommendations: ['ركبة Polycentric', 'Pin Lock', 'غطاء تجميلي'], diagnosis: 'بتر فخذي — توصية بركبة polycentric مع نظام Pin Lock.', doctor: 'د. ياسمين رشدي', date: '05/06/2026', status: 'معتمد', locked: true },
      { name: 'ليلى حسام الدين', nationalId: '30102001112233', company: 'صندوق ذوي الإعاقة', recommendations: ['قدم Carbon Spring', 'جوارب تجويف'], diagnosis: 'بتر قدم — مناسبة لقدم carbon spring مع جوارب تجويف.', doctor: 'د. سارة عبدالله', date: '04/06/2026', status: 'معتمد', locked: true },
      { name: 'كريم محمد علي', nationalId: '27808015678901', company: 'مجلس الدفاع المدني', recommendations: ['ركبة هيدروليكية', 'بطانة Gel', 'محول Pyramidal'], diagnosis: 'بتر فخذي — حالة cicatrization مستقرة. توصية بركبة هيدروليكية.', doctor: 'د. سارة عبدالله', date: '03/06/2026', status: 'معتمد', locked: true }
    ];

    var transferred = [
      { name: 'سارة أحمد فؤاد', recommendations: ['ركبة هيدروليكية', 'قدم Carbon Spring'], company: 'التأمين الوطني', date: '07/06/2026', status: 'في الورشة' },
      { name: 'هدى محمود سعيد', recommendations: ['مفصل كوع', 'محول Pyramidal'], company: 'ذوي الإعاقة', date: '06/06/2026', status: 'قيد التوصيف' },
      { name: 'أحمد فاروق نبيل', recommendations: ['ركبة Polycentric', 'Pin Lock'], company: 'مصر للتأمين', date: '05/06/2026', status: 'مكتمل' }
    ];

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

    function switchSection(sectionId) {
      document.querySelectorAll('.section-view').forEach(function(el) {
        el.classList.toggle('active', el.id === 'section-' + sectionId);
      });
      document.querySelectorAll('.nav-menu a[data-section]').forEach(function(a) {
        a.classList.toggle('active', a.getAttribute('data-section') === sectionId);
      });
      document.getElementById('pageTitle').textContent = sectionTitles[sectionId];
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
        e.preventDefault();
        switchSection(link.getAttribute('data-section'));
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
      ChartKit.mount('analytics-queue', {
        stats: [
          { icon: '📋', label: 'قائمة الانتظار', value: queue.length, bg: 'rgba(14,116,144,0.1)' },
          { icon: '🚨', label: 'عاجل', value: queue.filter(function(q){return q.priority==='urgent';}).length, color: '#dc2626', bg: 'rgba(220,38,38,0.1)' },
          { icon: '⏳', label: 'عادي', value: queue.filter(function(q){return q.priority==='normal';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '⏱️', label: 'متوسط الانتظار', value: '19د', color: '#d97706', bg: 'rgba(217,119,6,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'الأولوية', wide: true, large: true, items: [
            { label: 'عاجل', value: queue.filter(function(q){return q.priority==='urgent';}).length, color: '#dc2626' },
            { label: 'عادي', value: queue.filter(function(q){return q.priority==='normal';}).length, color: '#059669' }
          ], summary: [
            { label: 'عاجل', value: queue.filter(function(q){return q.priority==='urgent';}).length + ' حالة', color: '#dc2626' },
            { label: 'عادي', value: queue.filter(function(q){return q.priority==='normal';}).length + ' حالة', color: '#059669' },
            { label: 'الإجمالي', value: queue.length + ' مريض' }
          ]}
        ]
      });
      ChartKit.mount('analytics-diagnosis', {
        stats: [
          { icon: '📝', label: 'تقارير اليوم', value: 3, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '📦', label: 'أصناف المخزون', value: StockCatalog.getAll().length, bg: 'rgba(14,116,144,0.1)' },
          { icon: '💊', label: 'توصيات', value: medicalRecords.reduce(function(s, r) {
            return s + (r.recommendations || []).reduce(function(t, rec) { return t + normalizeRec(rec).qty; }, 0);
          }, 0), bg: 'rgba(14,116,144,0.1)' },
          { icon: '📦', label: 'محول للمخزون', value: transferred.length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'الأصناف الأكثر توصية', color: '#0e7490', items: countRecommendedItems(medicalRecords) },
          { type: 'column', title: 'فحوصات الأسبوع', color: '#0e7490', unit: 'count', items: [
            { label: 'السبت', value: 4, display: '4 فحوصات', sub: '→' },
            { label: 'الأحد', value: 6, display: '6 فحوصات', sub: '↑ مرتفع' },
            { label: 'الإثنين', value: 5, display: '5 فحوصات', sub: '→' },
            { label: 'الثلاثاء', value: 7, display: '7 فحوصات', sub: '↑ الأعلى' },
            { label: 'الأربعاء', value: 3, display: '3 فحوصات', sub: '↓ أقل يوم' }
          ], footer: 'إجمالي الأسبوع: <strong>25 فحص</strong> · متوسط يومي: <strong>5</strong> · الأعلى: <strong>الثلاثاء (7)</strong>' }
        ]
      });
      ChartKit.mount('analytics-records', {
        stats: [
          { icon: '📁', label: 'تقارير', value: medicalRecords.length, bg: 'rgba(14,116,144,0.1)' },
          { icon: '✅', label: 'معتمد', value: medicalRecords.filter(function(r){return r.locked;}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' },
          { icon: '💊', label: 'متوسط التوصيات', value: Math.round(medicalRecords.reduce(function(s, r) {
            return s + (r.recommendations || []).reduce(function(t, rec) { return t + normalizeRec(rec).qty; }, 0);
          }, 0) / Math.max(medicalRecords.length, 1)), bg: 'rgba(14,116,144,0.1)' },
          { icon: '📦', label: 'أصناف مختلفة', value: countRecommendedItems(medicalRecords).length, bg: 'rgba(14,116,144,0.1)' }
        ],
        charts: [
          { type: 'bar', title: 'توزيع التوصيات', color: '#0e7490', items: countRecommendedItems(medicalRecords) },
          { type: 'donut', title: 'حالة التقارير', items: [
            { label: 'معتمد', value: medicalRecords.filter(function(r){return r.locked;}).length, color: '#059669' },
            { label: 'إجمالي', value: medicalRecords.length, color: '#0e7490' }
          ]}
        ]
      });
      ChartKit.mount('analytics-transfer', {
        stats: [
          { icon: '🔧', label: 'محول', value: transferred.length, bg: 'rgba(14,116,144,0.1)' },
          { icon: '⚙️', label: 'قيد التوصيف', value: transferred.filter(function(t){return t.status.indexOf('توصيف')!==-1;}).length, color: '#d97706', bg: 'rgba(217,119,6,0.1)' },
          { icon: '🏭', label: 'في الورشة', value: transferred.filter(function(t){return t.status.indexOf('ورشة')!==-1;}).length, color: '#0e7490', bg: 'rgba(14,116,144,0.1)' },
          { icon: '✅', label: 'مكتمل', value: transferred.filter(function(t){return t.status==='مكتمل';}).length, color: '#059669', bg: 'rgba(5,150,105,0.1)' }
        ],
        charts: [
          { type: 'donut', title: 'حالة التحويل', items: transferred.map(function(t,i){
            return { label: t.status, value: 1, color: ['#d97706','#0e7490','#059669'][i]||'#64748b' };
          })},
          { type: 'bar', title: 'الأصناف المحولة', color: '#0e7490', items: countRecommendedItems(transferred) }
        ]
      });
    }
    renderDoctorAnalytics();
    renderQueue();
    renderRecords();
    renderTransferred();
