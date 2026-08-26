@php
    $deskSectionId = $desk_section_id ?? 'section-receive-inbound';
    $deskTitle = $desk_title ?? '📥 استلام الوارد — تسجيل فاتورة توريد';
    $receiveUrl = $receive_url ?? '/technical/inventory/receive';
@endphp
<div class="section-view" id="{{ $deskSectionId }}">
    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>{{ $deskTitle }}</h3>
        </div>
        <p style="padding:0 16px 8px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.6;">
            سجّل فاتورة توريد واردة لتحديث الرصيد والتكلفة عبر آلية الاستلام القياسية في المخزن.
        </p>
        <form id="inventoryReceiveForm" class="panel-body" style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <div class="form-group">
                <label>الصنف</label>
                <select id="receiveStockItemId" class="form-control" required>
                    <option value="">— اختر —</option>
                    @foreach ($inventory_items ?? [] as $item)
                        <option value="{{ $item['id'] ?? $item->id }}">{{ ($item['code'] ?? $item->code) }} — {{ ($item['name'] ?? $item->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الكمية</label>
                <input type="number" min="1" id="receiveQty" class="form-control" required>
            </div>
            <div class="form-group">
                <label>سعر الوحدة</label>
                <input type="number" min="0.01" step="0.01" id="receiveUnitPrice" class="form-control" required>
            </div>
            <div class="form-group">
                <label>المورد</label>
                <select id="receiveSupplierId" class="form-control" required>
                    <option value="">— اختر —</option>
                    @foreach ($inventory_suppliers ?? [] as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>رقم الفاتورة</label>
                <input type="text" id="receiveInvoiceNo" class="form-control" required maxlength="100">
            </div>
            <div class="form-group">
                <label>تاريخ الاستلام</label>
                <input type="date" id="receiveMovedAt" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            @if ($inbound_document_upload ?? true)
            <div class="form-group">
                <label>مرفق الفاتورة (PDF/صورة)</label>
                <input type="file" id="receiveDocument" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            @endif
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn-action success" id="btnSubmitReceive">💾 تسجيل الاستلام</button>
            </div>
        </form>
        <div id="receiveFormMessage" style="padding:0 16px 16px;display:none;"></div>
    </div>
</div>
<script>
window.__INBOUND_RECEIVE_ENABLED = @json($inbound_document_upload ?? true);
window.__INVENTORY_RECEIVE_URL = @json($receiveUrl);
</script>
@include('partials.inventory-receive-form-script')
