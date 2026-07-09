@php
    $showToggle = $show_toggle ?? true;
    $expanded = old('form') === 'patient' || ! $showToggle;
@endphp
<section class="add-patient-section {{ $showToggle ? '' : 'add-patient-section--inline' }} {{ $expanded ? 'expanded' : '' }}" id="addPatientSection">
    @if ($showToggle)
        <button type="button" class="add-patient-toggle" id="btnAddPatient" aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="addPatientFormWrap">
            <span class="add-patient-toggle-icon">➕</span>
            <span class="add-patient-toggle-text">
                <strong>إضافة مريض</strong>
                <small>تسجيل ملف جديد — اضغط لفتح النموذج</small>
            </span>
            <span class="add-patient-chevron" id="addPatientChevron">▼</span>
        </button>
    @endif
    <div class="add-patient-form-wrap {{ $expanded ? 'open' : '' }}" id="addPatientFormWrap">
        <div class="add-patient-form-body">
            <h4>➕ تسجيل مريض جديد</h4>
            <form method="POST" action="{{ route('reception.patients.store') }}" id="addPatientForm" data-validate-form>
                @csrf
                <input type="hidden" name="form" value="patient">
                @if ($errors->any() && old('form') === 'patient')
                    <div class="v-error-msg" style="margin-bottom:12px;" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                <div class="add-patient-form-grid">
                    <div class="form-group">
                        <label>اسم المريض <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="name" id="newPatientName" placeholder="الاسم الكامل"
                               value="{{ old('name') }}" data-v-rules="required,min:2,max:255" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>رقم الهاتف <span class="field-optional">(اختياري)</span></label>
                        <input type="tel" class="form-control @error('phone') v-invalid @enderror" name="phone" id="newPhone" placeholder="01xxxxxxxxx — يمكن تركه فارغاً"
                               maxlength="11" inputmode="numeric" value="{{ old('phone') }}"
                               data-v-digits-only="1" data-v-rules="egyptian-mobile" autocomplete="tel">
                        @error('phone')<div class="v-error-msg" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>الرقم القومي</label>
                        <input type="text" class="form-control @error('national_id') v-invalid @enderror" name="national_id" id="newNationalId" placeholder="14 رقم"
                               maxlength="14" inputmode="numeric" pattern="[0-9]*" value="{{ old('national_id') }}"
                               data-v-digits-only="1" data-v-rules="egyptian-national-id" autocomplete="off">
                        @error('national_id')<div class="v-error-msg" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>تصنيف المريض <span style="color:red">*</span></label>
                        @php
                            $classification = old('patient_classification');
                            if (! $classification) {
                                if (old('patient_type') === 'military') {
                                    $classification = 'military';
                                } elseif (old('contract_company_id')) {
                                    $classification = 'entity';
                                } else {
                                    $classification = 'cash';
                                }
                            }
                        @endphp
                        <select class="form-control" name="patient_classification" id="newPatientClassification" data-v-rules="required,select">
                            <option value="cash" @selected($classification === 'cash')>💵 مدني — كاش</option>
                            <option value="entity" @selected($classification === 'entity')>🏢 جهات</option>
                            <option value="military" @selected($classification === 'military')>🪖 عسكري</option>
                        </select>
                    </div>
                    <div class="form-group" id="grpEntityBilling" style="display:{{ $classification === 'entity' ? '' : 'none' }};">
                        <label>نوع الجهة <span style="color:red">*</span></label>
                        <select class="form-control" name="entity_billing_type" id="newEntityBillingType"
                                data-v-rules="required,select" data-v-when="patient_classification=entity">
                            <option value="">— اختر —</option>
                            <option value="contracted" @selected(old('entity_billing_type') === 'contracted')>📑 متعاقد</option>
                            <option value="non_contracted" @selected(old('entity_billing_type') === 'non_contracted')>🏷️ غير متعاقد</option>
                        </select>
                    </div>
                    <div class="form-group" id="grpRank" style="display:{{ $classification === 'military' ? '' : 'none' }};">
                        <label>الرتبة العسكرية <span style="color:red">*</span></label>
                        <select class="form-control" name="military_rank_id" id="newRankId"
                                data-v-rules="required,select" data-v-when="patient_classification=military">
                            <option value="">— اختر الرتبة —</option>
                            @foreach ($military_ranks ?? [] as $rank)
                                <option value="{{ $rank->id }}" @selected((string) old('military_rank_id') === (string) $rank->id)>
                                    {{ $rank->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="grpMilitaryNumber" style="display:{{ $classification === 'military' ? '' : 'none' }};">
                        <label>الرقم العسكري <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="military_number" id="newMilitaryNumber"
                               placeholder="رقم العسكري" maxlength="30" value="{{ old('military_number') }}"
                               data-v-rules="required,min:1,max:30" data-v-when="patient_classification=military">
                    </div>
                    <div class="form-group" id="grpSeniorityNumber" style="display:{{ $classification === 'military' ? '' : 'none' }};">
                        <label>رقم الأقدمية <span style="color:red">*</span></label>
                        <input type="text" class="form-control" name="seniority_number" id="newSeniorityNumber"
                               placeholder="رقم الأقدمية" maxlength="30" value="{{ old('seniority_number') }}"
                               data-v-rules="required,min:1,max:30" data-v-when="patient_classification=military">
                    </div>
                    <div class="form-group" id="grpMilitaryWeapon" style="display:{{ $classification === 'military' ? '' : 'none' }};">
                        <label>السلاح / الفرع <span style="color:red">*</span></label>
                        <select class="form-control" name="military_weapon" id="newMilitaryWeapon"
                                data-v-rules="required,select" data-v-when="patient_classification=military">
                            <option value="">— اختر السلاح —</option>
                            @foreach ($military_weapons ?? [] as $weapon)
                                <option value="{{ $weapon }}" @selected(old('military_weapon') === $weapon)>{{ $weapon }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="grpCompany" style="display:{{ ($classification === 'entity' && old('entity_billing_type')) ? '' : 'none' }};">
                        <label>جهة التعاقد <span style="color:red">*</span></label>
                        <select class="form-control" name="contract_company_id" id="newCompanyId">
                            <option value="">— اختر الجهة —</option>
                            @php
                                $contracted = collect($civilian_companies ?? [])->where('is_contracted', true);
                                $nonContracted = collect($civilian_companies ?? [])->where('is_contracted', false);
                            @endphp
                            @foreach ($contracted as $co)
                                <option value="{{ $co->id }}" data-is-contracted="1"
                                    @selected((string) old('contract_company_id') === (string) $co->id)>
                                    {{ $co->name }}
                                </option>
                            @endforeach
                            @foreach ($nonContracted as $co)
                                <option value="{{ $co->id }}" data-is-contracted="0"
                                    @selected((string) old('contract_company_id') === (string) $co->id)>
                                    {{ $co->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="grpNewCompany" class="add-company-inline" style="display:none;margin-top:10px;">
                            <label class="add-company-inline__label">أو أضف جهة غير متعاقدة جديدة</label>
                            <div class="add-company-inline__row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:stretch;">
                                <input type="text" class="form-control" id="newCompanyName" placeholder="اسم الجهة الجديدة"
                                       maxlength="255" autocomplete="off" style="flex:1;min-width:180px;">
                                <button type="button" class="btn btn-secondary" id="btnAddNewCompany" style="white-space:nowrap;">➕ إضافة للقائمة</button>
                            </div>
                            <p class="field-hint" id="newCompanyAddStatus" style="margin:6px 0 0;font-size:12px;color:var(--text-muted);"></p>
                        </div>
                    </div>
                    <div class="form-group" id="grpCashHint" style="display:{{ $classification === 'cash' ? '' : 'none' }};">
                        <label>الفوترة</label>
                        <p class="field-hint" style="font-size:13px;color:var(--text-muted);margin:0;padding:10px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                            💵 <strong>كاش</strong> — المريض يتحاسب نقداً على حسابه الشخصي.
                        </p>
                    </div>
                    <div class="form-group" id="grpVisitType">
                        <label>نوع الزيارة <span style="color:red">*</span></label>
                        <select class="form-control" name="visit_type_id" id="newVisitTypeId" data-v-rules="required,select">
                            <option value="">— اختر نوع الزيارة —</option>
                            @foreach ($visit_types ?? [] as $visitType)
                                <option value="{{ $visitType->id }}" @selected((string) old('visit_type_id') === (string) $visitType->id)>
                                    {{ $visitType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="add-patient-form-actions">
                    <button class="btn btn-secondary" type="button" id="btnCancelAddPatient">إلغاء</button>
                    <button class="btn btn-primary" type="submit" id="btnSavePatient">💾 حفظ وإضافة للجدولة</button>
                </div>
            </form>
        </div>
    </div>
</section>
