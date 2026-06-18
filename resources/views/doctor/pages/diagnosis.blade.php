<div class="section-view" id="section-diagnosis">
      <div id="analytics-diagnosis">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📝', 'label' => 'تقارير اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📦', 'label' => 'أصناف المخزون', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '💊', 'label' => 'توصيات', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📦', 'label' => 'محول للمخزون', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel form-panel">
        <div class="panel-header">
          <h3>📝 التشخيص الطبي</h3>
        </div>
        <div class="panel-body">
          <div class="patient-info-bar" id="patientBar">
            <h4 id="selectedPatientName">—</h4>
            <p id="selectedPatientInfo">—</p>
          </div>

          <div class="silent-clinic-note" id="silentClinicNote" style="display:none;">
            🪖 <span>مريض عسكري — <strong>عيادة صامتة</strong>: يُسجَّل الكشف والتوصيف ويتخطّى النظام عرض السعر والتحصيل (تقييم مالي صامت في الخلفية).</span>
          </div>

          <form id="diagnosisForm">
            <div class="form-group">
              <label>التوصيات الطبية <span class="required">*</span></label>
              <div class="stock-multi-select" id="medicalRecommendationsSelect">
                <div class="sms-selected"></div>
                <div class="sms-control">
                  <input type="text" class="sms-search" placeholder="🔍 ابحث واختر من أصناف المخزون..." autocomplete="off">
                  <button type="button" class="sms-toggle" aria-label="فتح القائمة">▼</button>
                </div>
                <div class="sms-dropdown"></div>
              </div>
              <p class="field-hint">اختيار متعدد من المخزون — حدّد <strong>الكمية</strong> لكل صنف (بحد أقصى المتوفر)</p>
            </div>

            <div class="form-group">
              <label>التشخيص الدقيق <span class="required">*</span></label>
              <textarea class="form-control" id="diagnosis" placeholder="أدخل التشخيص الطبي التفصيلي..." required></textarea>
            </div>

            <div class="form-group">
              <label>الروشتة الطبية</label>
              <textarea class="form-control" id="prescription" placeholder="الأدوية والإرشادات الطبية (اختياري)..."></textarea>
            </div>

            <!-- <div class="blocked-notice">
              🔒 الأسعار والتكاليف المالية محجوبة — لا تظهر في هذه الشاشة
            </div> -->

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                💾 حفظ واعتماد التقرير
              </button>
              <button type="button" class="btn btn-transfer" id="transferBtn" disabled>
                📦 تحويل للمخزون
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
