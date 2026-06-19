<div class="appointments-calendar-top visible" id="appointmentsCalendarWrap">
      <div class="calendar-panel">
        <div class="calendar-toolbar">
          <div class="calendar-header">
            <button type="button" class="cal-nav-btn" id="calPrev" aria-label="الشهر السابق">›</button>
            <h3 id="calMonthLabel"></h3>
            <button type="button" class="cal-nav-btn" id="calNext" aria-label="الشهر التالي">‹</button>
          </div>
          <p class="calendar-hint">اختر يوماً من اليوم أو ما قبله (حتى سنة) — الأيام المستقبلية غير متاحة</p>
          <button type="button" class="calendar-today-btn" id="calToday">📅 مواعيد اليوم</button>
        </div>
        <div class="calendar-body">
          <div class="calendar-weekdays">
            <span>أ</span><span>إ</span><span>ث</span><span>أ</span><span>خ</span><span>ج</span><span>س</span>
          </div>
          <div class="calendar-grid" id="calendarGrid"></div>
        </div>
      </div>
    </div>
<section class="add-patient-section {{ old('form') === 'patient' ? 'expanded' : '' }}" id="addPatientSection">
      <button type="button" class="add-patient-toggle" id="btnAddPatient" aria-expanded="false" aria-controls="addPatientFormWrap">
        <span class="add-patient-toggle-icon">➕</span>
        <span class="add-patient-toggle-text">
          <strong>إضافة مريض</strong>
          <small>تسجيل ملف جديد — اضغط لفتح النموذج</small>
        </span>
        <span class="add-patient-chevron" id="addPatientChevron">▼</span>
      </button>
      <div class="add-patient-form-wrap {{ old('form') === 'patient' ? 'open' : '' }}" id="addPatientFormWrap">
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
                <label>رقم الهاتف</label>
                <input type="tel" class="form-control @error('phone') v-invalid @enderror" name="phone" id="newPhone" placeholder="01xxxxxxxxx"
                       maxlength="11" inputmode="numeric" value="{{ old('phone') }}"
                       data-v-rules="egyptian-mobile" autocomplete="tel">
                @error('phone')<div class="v-error-msg" role="alert">{{ $message }}</div>@enderror
              </div>
              <div class="form-group">
                <label>الرقم القومي</label>
                <input type="text" class="form-control @error('national_id') v-invalid @enderror" name="national_id" id="newNationalId" placeholder="14 رقم"
                       maxlength="14" inputmode="numeric" value="{{ old('national_id') }}"
                       data-v-rules="egyptian-national-id" autocomplete="off">
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
              <div class="form-group" id="grpSovereign" style="display:{{ old('patient_type') === 'military' ? '' : 'none' }};">
                <label>الجهة السيادية <span style="color:red">*</span></label>
                <input type="text" class="form-control" name="sovereign_entity" id="newSovereignEntity"
                       placeholder="مثال: القوات المسلحة / الشرطة" value="{{ old('sovereign_entity') }}"
                       data-v-rules="required,min:2,max:255" data-v-when="patient_type=military" maxlength="255">
              </div>
              <div class="form-group" id="grpCompany">
                <label>جهة التعاقد <span id="companyRequired" style="color:red">*</span></label>
                <select class="form-control" name="contract_company_id" id="newCompanyId"
                        data-v-rules="required,select" data-v-when="patient_type=civilian">
                  <option value="">— اختر الجهة —</option>
                  @foreach ($civilian_companies ?? [] as $co)
                    <option value="{{ $co->id }}" data-military="0"
                        @selected((string) old('contract_company_id') === (string) $co->id)>
                      {{ $co->name }}
                    </option>
                  @endforeach
                  @foreach ($military_companies ?? [] as $co)
                    <option value="{{ $co->id }}" data-military="1" style="display:none"
                        @selected((string) old('contract_company_id') === (string) $co->id)>
                      {{ $co->name }}
                    </option>
                  @endforeach
                </select>
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
<div id="analytics-reception-main">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📅', 'label' => 'مواعيد اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '⏳', 'label' => 'انتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '👤', 'label' => 'مرضى', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
      ['icon' => '🧾', 'label' => 'عروض سعر', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
    ]])</div>

<div class="tab-content" id="tab-appointments">
      <div id="analytics-appointments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📅', 'label' => 'إجمالي', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🏥', 'label' => 'في العيادة', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '⏳', 'label' => 'انتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '✅', 'label' => 'مكتمل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ]])</div>
      <div class="appointments-layout">
        <div class="panel">
          <div class="panel-header">
            <h3 id="apptPanelTitle">📅 مواعيد</h3>
            <span style="font-size:12px;font-weight:600;color:var(--primary);" id="apptHeaderCount">0 موعد</span>
          </div>
          <div class="data-toolbar">
            <input type="text" id="apptSearch" placeholder="🔍 بحث بالاسم أو رقم الهاتف...">
            <select id="apptStatusFilter">
              <option value="all">كل الحالات</option>
              <option value="waiting">انتظار</option>
              <option value="in_clinic">في العيادة</option>
              <option value="quoted">عرض سعر</option>
              <option value="done">مكتمل</option>
            </select>
            <span class="toolbar-count" id="apptCount">0 موعد</span>
            <div class="export-btns">
              <button class="btn-export excel" onclick="exportAppointments('excel')">📊 Excel</button>
              <button class="btn-export pdf" onclick="exportAppointments('pdf')">📄 PDF</button>
            </div>
          </div>
          <div class="panel-body">
            <table data-paginate="10">
              <thead>
                <tr>
                  <th>الوقت</th>
                  <th>اسم المريض</th>
                  <th>نوع الزيارة</th>
                  <th>رقم الهاتف</th>
                  <th>جهة التعاقد</th>
                  <th>الحالة</th>
                  <th>إجراء</th>
                </tr>
              </thead>
              <tbody id="appointmentsTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

@if (session('show_patient_card'))
<script>window.__SHOW_PATIENT_CARD_ID = {{ (int) session('show_patient_card') }};</script>
@endif
