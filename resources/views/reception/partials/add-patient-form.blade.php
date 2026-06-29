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
                        <select class="form-control" name="patient_type" id="newPatientType" data-v-rules="required,select">
                            <option value="civilian" @selected(old('patient_type', 'civilian') === 'civilian')>🌐 مدني</option>
                            <option value="military" @selected(old('patient_type') === 'military')>🪖 عسكري</option>
                        </select>
                    </div>
                    <div class="form-group" id="grpRank" style="display:{{ old('patient_type') === 'military' ? '' : 'none' }};">
                        <label>الرتبة العسكرية <span style="color:red">*</span></label>
                        <select class="form-control" name="military_rank_id" id="newRankId"
                                data-v-rules="required,select" data-v-when="patient_type=military">
                            <option value="">— اختر الرتبة —</option>
                            @foreach ($military_ranks ?? [] as $rank)
                                <option value="{{ $rank->id }}" @selected((string) old('military_rank_id') === (string) $rank->id)>
                                    {{ $rank->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="grpCompany" style="display:{{ old('patient_type') === 'military' ? 'none' : '' }};">
                        <label>الفوترة / جهة التعاقد <span class="field-optional">(اختياري)</span></label>
                        <select class="form-control" name="contract_company_id" id="newCompanyId"
                                data-v-when="patient_type=civilian">
                            <option value="">💵 نقدي — حساب شخصي</option>
                            @php
                                $contracted = collect($civilian_companies ?? [])->where('is_contracted', true);
                                $nonContracted = collect($civilian_companies ?? [])->where('is_contracted', false);
                            @endphp
                            @if ($contracted->isNotEmpty())
                                <optgroup label="📑 هيئات متعاقدة">
                                    @foreach ($contracted as $co)
                                        <option value="{{ $co->id }}"
                                            @selected((string) old('contract_company_id') === (string) $co->id)>
                                            {{ $co->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if ($nonContracted->isNotEmpty())
                                <optgroup label="🏷️ هيئات غير متعاقدة">
                                    @foreach ($nonContracted as $co)
                                        <option value="{{ $co->id }}"
                                            @selected((string) old('contract_company_id') === (string) $co->id)>
                                            {{ $co->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        <p class="field-hint" style="font-size:11px;color:var(--text-muted);margin-top:6px;">
                            بدون جهة = المريض يتحاسب <strong>نقداً</strong> على حسابه الشخصي.
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
