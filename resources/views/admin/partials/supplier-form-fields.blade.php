<div class="form-group" style="margin-bottom:14px;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المورد / الشركة <span style="color:#dc2626">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
           data-v-rules="required,min:2,max:255" maxlength="255"
           placeholder="مثال: Ottobock Egypt"
           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
</div>
<div class="form-group" style="margin-bottom:14px;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الهاتف</label>
    <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}"
           data-v-rules="egyptian-mobile" maxlength="11" inputmode="numeric"
           placeholder="01xxxxxxxxx"
           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
</div>
<div class="form-group" style="margin-bottom:14px;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الفاكس</label>
    <input type="text" name="fax" class="form-control" value="{{ old('fax') }}"
           maxlength="30"
           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
</div>
<div class="form-group" style="margin-bottom:14px;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">البريد الإلكتروني</label>
    <input type="email" name="email" class="form-control" value="{{ old('email') }}"
           maxlength="191" placeholder="supplier@example.com"
           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
</div>
<div class="form-group" style="margin-bottom:14px;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">العنوان بالتفصيل</label>
    <textarea name="address" class="form-control" rows="2" maxlength="1000"
              placeholder="الشارع، المنطقة، المحافظة..."
              style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;">{{ old('address') }}</textarea>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
    <div class="form-group">
        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الرقم الضريبي</label>
        <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number') }}"
               maxlength="50"
               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
    <div class="form-group">
        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">السجل التجاري</label>
        <input type="text" name="commercial_registry" class="form-control" value="{{ old('commercial_registry') }}"
               maxlength="50"
               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
</div>
<fieldset style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:14px;">
    <legend style="font-size:13px;font-weight:700;padding:0 6px;">البيانات البنكية</legend>
    <div class="form-group" style="margin-bottom:10px;">
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">اسم البنك</label>
        <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name') }}"
               maxlength="191"
               style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
    <div class="form-group" style="margin-bottom:10px;">
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">الفرع</label>
        <input type="text" name="bank_branch" class="form-control" value="{{ old('bank_branch') }}"
               maxlength="191"
               style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
    <div class="form-group" style="margin-bottom:10px;">
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">رقم الحساب</label>
        <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account') }}"
               maxlength="64"
               style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">IBAN</label>
        <input type="text" name="iban" class="form-control" value="{{ old('iban') }}"
               maxlength="34"
               style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
    </div>
</fieldset>
<div class="form-group" style="margin-bottom:0;">
    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">ملاحظات</label>
    <textarea name="notes" class="form-control" rows="2" maxlength="1000"
              style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;resize:vertical;">{{ old('notes') }}</textarea>
</div>
