@php($isSuper = auth()->user()?->isSuperAdmin() ?? false)
<div class="section-view" id="section-data-purge">
    @unless ($isSuper)
        <div class="panel" style="padding:24px;text-align:center;color:#b91c1c;font-weight:700;">حذف البيانات من صلاحيات مدير النظام فقط.</div>
    @else
    <div class="panel" style="margin-bottom:16px;border:1px solid #fecaca;">
        <div style="padding:14px 16px;background:#fef2f2;color:#991b1b;font-size:14px;line-height:1.8;">
            ⚠️ <strong>الحذف نهائي ولا يمكن التراجع عنه</strong> إلا من النسخة الاحتياطية. كل عملية تتطلب كلمة مرورك، ويتم أخذ نسخة احتياطية تلقائياً قبل الحذف.
            سجل الرقابة لا يُحذف أبداً، ويُسجَّل فيه كل حذف باسمك.
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>🧑‍🦽 حذف مرضى</h3>
            <span class="badge" id="dpPatientsTotal">—</span>
        </div>
        <div style="padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="search" id="dpSearch" class="form-control" style="max-width:320px;" placeholder="بحث بالاسم أو الكود أو الهاتف أو الرقم القومي…">
            <span id="dpSelectedCount" style="font-weight:700;">المحدد: 0</span>
            <button type="button" class="btn-action danger" id="dpDeleteSelected" disabled>🗑️ حذف المحدد</button>
            <button type="button" class="btn-action danger" data-dp-target="patients-all">🗑️ حذف كل المرضى</button>
        </div>
        <div class="stock-table-wrap" style="padding:0 16px 16px;max-height:520px;overflow:auto;">
            <table data-no-paginate class="stock-table" style="width:100%;">
                <thead><tr>
                    <th style="width:40px;"><input type="checkbox" id="dpCheckAll" title="تحديد الكل"></th>
                    <th>الكود</th><th>الاسم</th><th>النوع</th><th>الهاتف</th><th>تاريخ التسجيل</th><th>الحالات</th><th>المدفوع</th>
                </tr></thead>
                <tbody id="dpPatientsBody"><tr><td colspan="8" class="empty-cell">جاري التحميل…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3>🧹 حذف بيانات أخرى</h3></div>
        <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
            @foreach ([
                ['stock-catalog', '📦 كتالوج الأصناف', 'كل الأصناف وحركات المخزن ودفعات الأسعار وطلبات التوريد والاستلام والتبديل. يُفضَّل حذف المرضى أولاً.'],
                ['users', '👥 حسابات الموظفين', 'كل حسابات الموظفين — يبقى حساب مدير النظام والأدوار والصلاحيات.'],
                ['notifications', '🔔 الإشعارات', 'كل الإشعارات لكل المستخدمين.'],
                ['everything', '♻️ بداية جديدة', 'كل المرضى وحالاتهم + كل الأصناف والمخزن. يبقى: المستخدمون والموردون والإعدادات.'],
            ] as [$key, $title, $desc])
                <div style="border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:8px;">
                    <strong>{{ $title }}</strong>
                    <span style="font-size:13px;color:var(--text-muted);line-height:1.7;flex:1;">{{ $desc }}</span>
                    <button type="button" class="btn-action danger" data-dp-target="{{ $key }}">🗑️ حذف</button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- تأكيد بكلمة المرور --}}
    <div class="dp-modal-overlay" id="dpConfirmModal">
        <div class="dp-modal">
            <div class="modal-header"><h3>🔐 تأكيد الحذف</h3><button type="button" class="modal-close" id="dpConfirmClose">&times;</button></div>
            <div class="modal-body" style="padding:16px;">
                <p id="dpConfirmText" style="margin:0 0 12px;font-weight:700;color:#991b1b;line-height:1.8;"></p>
                <div class="form-group">
                    <label for="dpPassword">كلمة مرورك</label>
                    <input type="password" id="dpPassword" class="form-control" autocomplete="current-password">
                </div>
                <label style="display:flex;gap:6px;align-items:center;font-size:13px;margin-top:8px;">
                    <input type="checkbox" id="dpBackup" checked> أخذ نسخة احتياطية قبل الحذف (مُستحسن)
                </label>
                <p id="dpConfirmError" style="color:#b91c1c;font-weight:700;margin:10px 0 0;"></p>
            </div>
            <div class="modal-footer" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn-view" id="dpConfirmCancel">إلغاء</button>
                <button type="button" class="btn-action danger" id="dpConfirmGo">🗑️ تأكيد الحذف</button>
            </div>
        </div>
    </div>
    <p id="dpMessage" style="margin:12px 4px;font-weight:700;"></p>
    <style>
        .dp-modal-overlay { display:none; position:fixed; inset:0; z-index:1000; background:rgba(15,23,42,.55); align-items:center; justify-content:center; padding:16px; }
        .dp-modal-overlay.visible { display:flex; }
        .dp-modal { background:var(--surface, #fff); border-radius:16px; width:100%; max-width:460px; box-shadow:0 20px 50px rgba(0,0,0,.25); overflow:hidden; }
        .dp-modal .modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid var(--border); }
        .dp-modal .modal-header h3 { margin:0; font-size:16px; }
        .dp-modal .modal-close { border:none; background:transparent; font-size:22px; cursor:pointer; }
        .dp-modal .modal-footer { border-top:1px solid var(--border); }
    </style>

    <script>
    (function () {
        var targets = @json(\App\Http\Controllers\Admin\DataPurgeController::TARGETS);
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var selected = {};
        var pending = null;
        function $(id) { return document.getElementById(id); }
        function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
        function selectedIds() { return Object.keys(selected).filter(function (k) { return selected[k]; }).map(Number); }
        function syncCount() {
            var n = selectedIds().length;
            $('dpSelectedCount').textContent = 'المحدد: ' + n;
            $('dpDeleteSelected').disabled = n === 0;
        }
        var timer;
        function load() {
            fetch('/admin/data-purge/patients?q=' + encodeURIComponent($('dpSearch').value.trim()), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    $('dpPatientsTotal').textContent = res.total + ' مريض';
                    var rows = res.data || [];
                    $('dpPatientsBody').innerHTML = rows.length ? rows.map(function (p) {
                        return '<tr><td><input type="checkbox" class="dp-check" value="' + p.id + '"' + (selected[p.id] ? ' checked' : '') + '></td>' +
                            '<td>' + esc(p.code) + '</td><td><strong>' + esc(p.name) + '</strong></td><td>' + esc(p.type) + '</td><td>' + esc(p.phone || '—') + '</td>' +
                            '<td>' + esc(p.registered_at || '—') + '</td><td>' + p.cases + '</td><td>' + (p.paid ? p.paid.toLocaleString('en-US') + ' ج.م' : '—') + '</td></tr>';
                    }).join('') : '<tr><td colspan="8" class="empty-cell">لا يوجد مرضى.</td></tr>';
                    $('dpCheckAll').checked = rows.length > 0 && rows.every(function (p) { return selected[p.id]; });
                    syncCount();
                });
        }
        $('dpSearch').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); });
        $('dpPatientsBody').addEventListener('change', function (e) {
            if (e.target.classList.contains('dp-check')) { selected[e.target.value] = e.target.checked; syncCount(); }
        });
        $('dpCheckAll').addEventListener('change', function () {
            var on = this.checked;
            document.querySelectorAll('.dp-check').forEach(function (c) { c.checked = on; selected[c.value] = on; });
            syncCount();
        });
        function openConfirm(text, action) {
            pending = action;
            $('dpConfirmText').textContent = text;
            $('dpPassword').value = '';
            $('dpConfirmError').textContent = '';
            $('dpConfirmModal').classList.add('visible');
            setTimeout(function () { $('dpPassword').focus(); }, 50);
        }
        function closeConfirm() { $('dpConfirmModal').classList.remove('visible'); pending = null; }
        $('dpConfirmClose').addEventListener('click', closeConfirm);
        $('dpConfirmCancel').addEventListener('click', closeConfirm);
        $('dpDeleteSelected').addEventListener('click', function () {
            var ids = selectedIds();
            openConfirm('سيتم حذف ' + ids.length + ' مريض نهائياً مع كل حالاتهم ومدفوعاتهم وقوائم موادهم، ويُعاد المصروف لهم إلى المخزن.', { url: '/admin/data-purge/patients', body: { ids: ids } });
        });
        document.querySelectorAll('[data-dp-target]').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.dataset.dpTarget;
                openConfirm('سيتم حذف: ' + targets[t] + ' — نهائياً.', { url: '/admin/data-purge/' + t, body: {} });
            });
        });
        $('dpConfirmGo').addEventListener('click', function () {
            if (!pending) return;
            var pw = $('dpPassword').value;
            if (!pw) { $('dpConfirmError').textContent = 'اكتب كلمة المرور.'; return; }
            var btn = this; btn.disabled = true;
            var body = Object.assign({}, pending.body, { password: pw, backup: $('dpBackup').checked });
            fetch(pending.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
              .then(function (res) {
                  closeConfirm(); selected = {};
                  $('dpMessage').style.color = '#059669'; $('dpMessage').textContent = '✅ ' + (res.message || 'تم الحذف.');
                  load();
              })
              .catch(function (e) {
                  $('dpConfirmError').textContent = (e && e.errors) ? Object.values(e.errors)[0][0] : ((e && e.message) || 'تعذّر الحذف.');
              })
              .finally(function () { btn.disabled = false; });
        });
        load();
    })();
    </script>
    @endunless
</div>
