{{-- Add Supplier Modal --}}
<div class="catalog-modal-overlay {{ ($openSupplierModal ?? false) ? 'open' : '' }}" id="supplierModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:560px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div><h3>➕ إضافة مورد</h3></div>
            <button type="button" class="catalog-modal-close" id="closeSupplierModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.suppliers.store') }}" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="supplier">
            <div class="catalog-modal-body" style="max-height:70vh;overflow-y:auto;">
                @if ($errors->any() && old('form') === 'supplier')
                    <div class="v-error-msg" style="margin-bottom:12px;" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @include('admin.partials.supplier-form-fields', ['prefix' => '', 'oldForm' => 'supplier'])

                <p style="font-size:12px;color:var(--text-muted);margin:14px 0 0;padding:10px;background:var(--surface-2,#f8fafc);border-radius:8px;">
                    💡 يتم ربط الأصناف بالمورد من شاشة <strong>الأصناف والأسعار</strong> عند إضافة أو تعديل كل صنف.
                </p>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelSupplierModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Supplier Modal --}}
<div class="catalog-modal-overlay" id="supplierEditModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" style="max-width:560px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div><h3>✏️ تعديل المورد</h3></div>
            <button type="button" class="catalog-modal-close" id="closeSupplierEditModal" aria-label="إغلاق">&times;</button>
        </div>
        <input type="hidden" id="editSupplierId">
        <div class="catalog-modal-body" style="max-height:70vh;overflow-y:auto;">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المورد / الشركة</label>
                <input type="text" id="editSupplierName" maxlength="255" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الهاتف</label>
                <input type="tel" id="editSupplierPhone" maxlength="11" inputmode="numeric" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الفاكس</label>
                <input type="text" id="editSupplierFax" maxlength="30" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">البريد الإلكتروني</label>
                <input type="email" id="editSupplierEmail" maxlength="191" class="form-control"
                       style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">العنوان بالتفصيل</label>
                <textarea id="editSupplierAddress" rows="2" maxlength="1000" class="form-control"
                          style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div class="form-group">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الرقم الضريبي</label>
                    <input type="text" id="editSupplierTax" maxlength="50" class="form-control"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">السجل التجاري</label>
                    <input type="text" id="editSupplierCommercial" maxlength="50" class="form-control"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </div>
            <fieldset style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:14px;">
                <legend style="font-size:13px;font-weight:700;padding:0 6px;">البيانات البنكية</legend>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">اسم البنك</label>
                    <input type="text" id="editSupplierBankName" maxlength="191" class="form-control"
                           style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">الفرع</label>
                    <input type="text" id="editSupplierBankBranch" maxlength="191" class="form-control"
                           style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">رقم الحساب</label>
                    <input type="text" id="editSupplierBankAccount" maxlength="64" class="form-control"
                           style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">IBAN</label>
                    <input type="text" id="editSupplierIban" maxlength="34" class="form-control"
                           style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </fieldset>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">ملاحظات</label>
                <textarea id="editSupplierNotes" rows="2" maxlength="1000" class="form-control"
                          style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;"></textarea>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:0;padding:10px;background:var(--surface-2,#f8fafc);border-radius:8px;">
                💡 لربط أصناف بهذا المورد، عدّل الصنف من شاشة <strong>الأصناف والأسعار</strong> واختر المورد هناك.
            </p>
            <div id="supplierEditError"
                 style="display:none;padding:10px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:13px;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelSupplierEditModal">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveSupplierEdit()">💾 حفظ</button>
        </div>
    </div>
</div>
