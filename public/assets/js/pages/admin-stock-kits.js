(function () {
  if (document.body.dataset.dashboard !== 'admin' || document.body.dataset.activePage !== 'stock-kits') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
  }

  var kits = [];
  var stockItems = [];

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function load() {
    axios.get('/admin/stock-kits/list').then(function (res) {
      kits = res.data.data || [];
      stockItems = res.data.stock_items || [];
      renderTable();
    }).catch(function () { alert('تعذّر تحميل الأطقم'); });
  }

  function renderTable() {
    var q = ($('stockKitSearch') && $('stockKitSearch').value || '').trim().toLowerCase();
    var tbody = $('stockKitsTable');
    if (!tbody) return;
    var rows = kits.filter(function (k) {
      if (!q) return true;
      return (k.name + ' ' + k.code).toLowerCase().indexOf(q) !== -1;
    });
    $('stockKitCount').textContent = rows.length + ' طقم';
    tbody.innerHTML = rows.map(function (k) {
      return '<tr data-id="' + k.id + '">' +
        '<td><strong>' + esc(k.name) + '</strong></td>' +
        '<td dir="ltr">' + esc(k.code) + '</td>' +
        '<td>' + esc(k.type_label) + '</td>' +
        '<td>' + (k.items || []).map(function (i) { return esc(i.name) + ' ×' + i.qty; }).join('<br>') + '</td>' +
        '<td>' + (k.is_active ? '✅ نشط' : '⏸️') + '</td>' +
        '<td><button type="button" class="btn-action kit-edit">✏️</button> ' +
        '<button type="button" class="btn-action danger kit-del">🗑️</button></td></tr>';
    }).join('') || '<tr><td colspan="6" class="empty-cell">لا توجد أطقم — أضف طقماً جديداً.</td></tr>';

    tbody.querySelectorAll('.kit-edit').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var kit = kits.find(function (k) { return k.id === id; });
        if (kit) openModal(kit);
      });
    });
    tbody.querySelectorAll('.kit-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        if (!confirm('حذف هذا الطقم؟')) return;
        axios.delete('/admin/stock-kits/' + id).then(load);
      });
    });
  }

  function componentRow(data) {
    var row = document.createElement('div');
    row.className = 'kit-comp-row';
    row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;align-items:center;';
    var opts = stockItems.map(function (s) {
      var sel = data && data.stock_item_id === s.id ? ' selected' : '';
      return '<option value="' + s.id + '"' + sel + '>' + esc(s.code + ' — ' + s.name) + '</option>';
    }).join('');
    row.innerHTML =
      '<select class="form-control kit-comp-item" style="flex:1">' + opts + '</select>' +
      '<input type="number" class="form-control kit-comp-qty" min="1" value="' + ((data && data.qty) || 1) + '" style="width:90px">' +
      '<button type="button" class="btn-action danger kit-comp-remove">✕</button>';
    row.querySelector('.kit-comp-remove').addEventListener('click', function () { row.remove(); });
    return row;
  }

  function openModal(kit) {
    $('stockKitId').value = kit ? kit.id : '';
    $('stockKitModalTitle').textContent = kit ? '✏️ تعديل طقم' : '➕ طقم جديد';
    $('stockKitName').value = kit ? kit.name : '';
    $('stockKitType').value = kit ? kit.type : 'assembly';
    $('stockKitDescription').value = kit ? (kit.description || '') : '';
    $('stockKitActive').checked = kit ? !!kit.is_active : true;
    var wrap = $('stockKitComponents');
    wrap.innerHTML = '';
    (kit && kit.items ? kit.items : [{}]).forEach(function (item) {
      wrap.appendChild(componentRow(item));
    });
    $('stockKitError').style.display = 'none';
    $('stockKitModal').classList.add('open');
    $('stockKitModal').removeAttribute('hidden');
  }

  function closeModal() {
    $('stockKitModal').classList.remove('open');
    $('stockKitModal').setAttribute('hidden', '');
  }

  function collectComponents() {
    var rows = [];
    document.querySelectorAll('#stockKitComponents .kit-comp-row').forEach(function (row) {
      var id = parseInt(row.querySelector('.kit-comp-item').value, 10);
      var qty = parseInt(row.querySelector('.kit-comp-qty').value, 10) || 1;
      if (id > 0) rows.push({ stock_item_id: id, qty: qty });
    });
    return rows;
  }

  function saveKit() {
    var id = $('stockKitId').value;
    var items = collectComponents();
    var err = $('stockKitError');
    if (!$('stockKitName').value.trim()) {
      err.textContent = 'اسم الطقم مطلوب.';
      err.style.display = 'block';
      return;
    }
    if (!items.length) {
      err.textContent = 'أضف مكوّناً واحداً على الأقل.';
      err.style.display = 'block';
      return;
    }
    var payload = {
      name: $('stockKitName').value.trim(),
      type: $('stockKitType').value,
      description: $('stockKitDescription').value.trim() || null,
      is_active: $('stockKitActive').checked,
      items: items,
    };
    var req = id
      ? axios.put('/admin/stock-kits/' + id, payload)
      : axios.post('/admin/stock-kits', payload);
    req.then(function () { closeModal(); load(); })
      .catch(function (e) {
        err.textContent = (e.response && e.response.data && e.response.data.message) || 'تعذّر الحفظ';
        err.style.display = 'block';
      });
  }

  $('btnAddStockKit') && $('btnAddStockKit').addEventListener('click', function () { openModal(null); });
  $('btnAddKitComponent') && $('btnAddKitComponent').addEventListener('click', function () {
    $('stockKitComponents').appendChild(componentRow(null));
  });
  $('saveStockKitBtn') && $('saveStockKitBtn').addEventListener('click', saveKit);
  $('cancelStockKitModal') && $('cancelStockKitModal').addEventListener('click', closeModal);
  $('closeStockKitModal') && $('closeStockKitModal').addEventListener('click', closeModal);
  $('stockKitModal') && $('stockKitModal').addEventListener('click', function (e) {
    if (e.target === $('stockKitModal')) closeModal();
  });
  $('stockKitSearch') && $('stockKitSearch').addEventListener('input', renderTable);

  load();
})();
