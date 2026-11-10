{{-- «اعتمادات المخزن» — طلبات التوريد واستلام الوارد بانتظار مسؤول المخازن (مشتركة بين الإدارة والمخزن). --}}
<div class="stock-approvals-desk">
    <div class="panel inventory-wrap" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>🛒 طلبات توريد بانتظار الاعتماد</h3>
            <span class="badge" id="saSupplyCount">0</span>
        </div>
        <div class="stock-table-wrap" style="padding:0 16px 16px;">
            <table data-no-paginate class="stock-table" style="width:100%;">
                <thead><tr><th>رقم الطلب</th><th>الأصناف</th><th>ملاحظات</th><th>طلبه</th><th>التاريخ</th><th style="width:190px;">إجراء</th></tr></thead>
                <tbody id="saSupplyBody"><tr><td colspan="6" class="empty-cell">جاري التحميل…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>📥 استلام وارد بانتظار الاعتماد</h3>
            <span class="badge" id="saReceiptCount">0</span>
        </div>
        <p style="margin:10px 16px 0;font-size:13px;color:var(--text-muted);">يُسجَّل الرصيد ودفعة السعر ومديونية المورد عند الاعتماد فقط.</p>
        <div class="stock-table-wrap" style="padding:0 16px 16px;">
            <table data-no-paginate class="stock-table" style="width:100%;">
                <thead><tr><th>الصنف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th>المورد / الفاتورة</th><th>طلب توريد</th><th>استلمه</th><th style="width:190px;">إجراء</th></tr></thead>
                <tbody id="saReceiptBody"><tr><td colspan="8" class="empty-cell">جاري التحميل…</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="panel inventory-wrap" style="margin-top:16px;">
        <div class="panel-header">
            <h3>🔁 تبديل أصناف مع المورد بانتظار الاعتماد</h3>
            <span class="badge" id="saSwapCount">0</span>
        </div>
        <p style="margin:10px 16px 0;font-size:13px;color:var(--text-muted);">عند الاعتماد يُخصم الصنف الخارج بتكلفته الفعلية، ويُضاف البديل بقيمة الخارج مضافاً إليها فرق السعر (وإن كان جديداً يُنشأ في الكتالوج). مديونية المورد تساوي فرق السعر فقط.</p>
        <div class="stock-table-wrap" style="padding:0 16px 16px;">
            <table data-no-paginate class="stock-table" style="width:100%;">
                <thead><tr><th>رقم التبديل</th><th>الخارج من مخزوننا</th><th>البديل الداخل</th><th>فرق السعر</th><th>المورد</th><th>طلبه</th><th style="width:190px;">إجراء</th></tr></thead>
                <tbody id="saSwapBody"><tr><td colspan="7" class="empty-cell">جاري التحميل…</td></tr></tbody>
            </table>
        </div>
    </div>
    <p id="saMessage" style="margin:12px 4px;font-weight:700;"></p>
</div>

<script>
(function () {
    var base = @json(url('/'.($dashboardKey ?? 'technical').'/stock-approvals'));
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function money(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function say(text, ok) { var m = document.getElementById('saMessage'); if (m) { m.style.color = ok ? '#059669' : '#dc2626'; m.textContent = text; } }
    function post(url, body) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body || {}) })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); });
    }
    function act(kind, id, decision) {
        var body = {};
        if (decision === 'reject') {
            var reason = window.prompt('سبب الرفض:');
            if (!reason || !reason.trim()) return;
            body.reason = reason.trim();
        } else if (!window.confirm(kind === 'receipts' ? 'اعتماد الاستلام وإضافة الرصيد؟' : (kind === 'swaps' ? 'اعتماد التبديل؟ سيُخصم الصنف الخارج ويُضاف البديل إلى المخزون.' : 'اعتماد طلب التوريد؟'))) {
            return;
        }
        post(base + '/' + kind + '/' + id + '/' + decision, body)
            .then(function (res) { say(res.message || 'تم', true); load(); })
            .catch(function (err) { say((err && err.message) || 'تعذّر التنفيذ', false); });
    }
    function buttons(kind, id, allowed) {
        if (!allowed) return '<span style="font-size:12px;color:var(--text-muted);">بانتظار مسؤول المخازن</span>';
        return '<button type="button" class="btn-action success" data-act="approve" data-kind="' + kind + '" data-id="' + id + '">✓ اعتماد</button> ' +
            '<button type="button" class="btn-action danger" data-act="reject" data-kind="' + kind + '" data-id="' + id + '">✕ رفض</button>';
    }
    function load() {
        fetch(base + '/pending', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var sup = d.supply_requests || [], rec = d.receipts || [];
                document.getElementById('saSupplyCount').textContent = sup.length;
                document.getElementById('saReceiptCount').textContent = rec.length;
                document.getElementById('saSupplyBody').innerHTML = sup.length ? sup.map(function (r) {
                    return '<tr><td><strong>' + esc(r.request_no) + '</strong></td><td>' + r.lines.map(function (l) {
                        return esc(l.label) + ' × ' + esc(l.qty) + (l.uom ? ' ' + esc(l.uom) : '') + (l.spec ? ' — ' + esc(l.spec) : '');
                    }).join('<br>') + '</td><td>' + esc(r.notes || '—') + '</td><td>' + esc(r.requested_by || '—') + '</td><td>' + esc(r.requested_at) + '</td><td>' + buttons('supply', r.id, d.can_approve_supply) + '</td></tr>';
                }).join('') : '<tr><td colspan="6" class="empty-cell">لا توجد طلبات توريد بانتظار الاعتماد.</td></tr>';
                document.getElementById('saReceiptBody').innerHTML = rec.length ? rec.map(function (r) {
                    return '<tr><td>' + esc(r.item) + '</td><td>' + esc(r.qty) + ' ' + esc(r.uom || '') + '</td><td>' + money(r.unit_price) + '</td><td><strong>' + money(r.total) + '</strong></td><td>' + esc(r.supplier || '—') + '<br><span style="font-size:12px;color:var(--text-muted);">فاتورة ' + esc(r.invoice_no) + ' · ' + esc(r.moved_at) + (r.has_document ? ' · 📎' : '') + '</span></td><td>' + esc(r.supply_request_no || '—') + '</td><td>' + esc(r.requested_by || '—') + '<br><span style="font-size:12px;color:var(--text-muted);">' + esc(r.requested_at) + '</span></td><td>' + buttons('receipts', r.id, d.can_approve_receipts) + '</td></tr>';
                }).join('') : '<tr><td colspan="8" class="empty-cell">لا يوجد استلام وارد بانتظار الاعتماد.</td></tr>';
                var sw = d.swaps || [];
                document.getElementById('saSwapCount').textContent = sw.length;
                document.getElementById('saSwapBody').innerHTML = sw.length ? sw.map(function (r) {
                    var shortage = r.out_qty > r.out_available ? '<br><span style="font-size:12px;color:#dc2626;">⚠ المتاح ' + esc(r.out_available) + ' فقط</span>' : '';
                    var spec = r.new_item ? [r.new_item.uom, r.new_item.brand, r.new_item.spec].filter(Boolean).map(esc).join(' · ') : '';
                    return '<tr><td><strong>' + esc(r.swap_no) + '</strong>' + (r.notes ? '<br><span style="font-size:12px;color:var(--text-muted);">' + esc(r.notes) + '</span>' : '') + '</td><td>' + esc(r.out) + ' × ' + esc(r.out_qty) + shortage + '</td><td>' + (r.in_is_new ? '<span class="badge" style="background:#ecfdf5;color:#047857;">جديد</span> ' : '') + esc(r.in) + ' × ' + esc(r.in_qty) + (spec ? '<br><span style="font-size:12px;color:var(--text-muted);">' + spec + '</span>' : '') + '</td><td>' + money(r.price_difference) + '</td><td>' + esc(r.supplier || '—') + '</td><td>' + esc(r.requested_by || '—') + '<br><span style="font-size:12px;color:var(--text-muted);">' + esc(r.requested_at) + '</span></td><td>' + buttons('swaps', r.id, d.can_manage_swaps) + '</td></tr>';
                }).join('') : '<tr><td colspan="7" class="empty-cell">لا توجد طلبات تبديل بانتظار الاعتماد.</td></tr>';
                document.querySelectorAll('.stock-approvals-desk [data-act]').forEach(function (b) {
                    b.addEventListener('click', function () { act(b.dataset.kind, b.dataset.id, b.dataset.act); });
                });
            })
            .catch(function () { say('تعذّر تحميل الاعتمادات', false); });
    }
    load();
})();
</script>
