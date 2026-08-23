@php
    $technicians = $workshop_technicians ?? [];
    $sections = $workshop_sections ?? [];
    $technicianCount = count($technicians);
    $sectionCount = count($sections);
@endphp

<div class="workshop-sections-page" id="workshopSectionsPage">
  <div class="ws-tabs" role="tablist" aria-label="أقسام الإنتاج والفنيين">
    <button type="button" data-ws-tab="sections" class="ws-tab-btn active" role="tab" aria-selected="true">
      🏭 الأقسام <span class="ws-muted">({{ $sectionCount }})</span>
    </button>
    <button type="button" data-ws-tab="technicians" class="ws-tab-btn" role="tab" aria-selected="false">
      👷 الفنيون <span class="ws-muted">({{ $technicianCount }})</span>
    </button>
  </div>

  {{-- تبويب الأقسام --}}
  <div id="wsTabSections" class="ws-tab-panel" role="tabpanel">
    <div class="panel-hint panel-hint--workshop">
      <div class="panel-hint__label">🏭 إدارة أقسام الإنتاج</div>
      <p class="panel-hint__text">
        أنشئ الأقسام وعدّلها أو احذفها. ربط الفنيين بالأقسام يتم من تبويب <strong>«الفنيون»</strong>.
      </p>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h3>🏭 أقسام الإنتاج <span class="badge">{{ $sectionCount }} قسم</span></h3>
        <button type="button" class="btn-add-rank" id="btnAddWorkshopSection">➕ إضافة قسم</button>
      </div>
      <div class="data-toolbar">
        <input type="text" id="workshopSectionSearch" placeholder="🔍 بحث باسم القسم أو الكود...">
        <span class="toolbar-count" id="workshopSectionCount">{{ $sectionCount }} قسم</span>
      </div>
      <div class="panel-body">
        <div class="bom-table-wrap">
          <table class="bom-table" data-paginate="10">
            <thead>
              <tr>
                <th>القسم</th>
                <th>الكود</th>
                <th>الوصف</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="workshopSectionsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- تبويب الفنيون --}}
  <div id="wsTabTechnicians" class="ws-tab-panel" role="tabpanel" hidden>
    <div class="panel-hint panel-hint--workshop">
      <div class="panel-hint__label">👷 إدارة فنيي الإنتاج</div>
      <p class="panel-hint__text">أضف فنيين جدد أو عدّل بياناتهم واربطهم بالأقسام المناسبة.</p>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h3>👷 فنيو الإنتاج <span class="badge">{{ $technicianCount }} فني</span></h3>
        <button type="button" class="btn-add-rank" id="btnAddWorkshopTechnician">➕ إضافة فني</button>
      </div>
      <div class="data-toolbar">
        <input type="text" id="workshopTechnicianSearch" placeholder="🔍 بحث بالاسم أو اسم المستخدم...">
        <span class="toolbar-count" id="workshopTechnicianCount">{{ $technicianCount }} فني</span>
      </div>
      <div class="panel-body">
        <div class="bom-table-wrap">
          <table class="bom-table" data-paginate="10">
            <thead>
              <tr>
                <th>الاسم</th>
                <th>اسم المستخدم</th>
                <th>الأقسام</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="workshopTechniciansTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- مودال القسم --}}
<div class="catalog-modal-overlay" id="workshopSectionModal" role="dialog" aria-modal="true" aria-labelledby="workshopSectionModalTitle">
  <div class="catalog-modal" style="max-width:520px;" onclick="event.stopPropagation()">
    <div class="catalog-modal-header">
      <div>
        <h3 id="workshopSectionModalTitle">➕ قسم إنتاج جديد</h3>
        <p class="ws-muted" style="margin:4px 0 0;font-size:12px;" id="workshopSectionModalHint">أدخل بيانات القسم.</p>
      </div>
      <button type="button" class="catalog-modal-close" id="closeWorkshopSectionModal" aria-label="إغلاق">&times;</button>
    </div>
    <div class="catalog-modal-body">
      <input type="hidden" id="workshopSectionId">
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopSectionName">اسم القسم <span style="color:#dc2626">*</span></label>
        <input type="text" id="workshopSectionName" class="form-control" maxlength="100" placeholder="مثال: تركيب المفاصل">
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopSectionCode">الكود (اختياري)</label>
        <input type="text" id="workshopSectionCode" class="form-control" maxlength="50" placeholder="JOINTS" dir="ltr">
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopSectionDescription">الوصف</label>
        <textarea id="workshopSectionDescription" class="form-control" rows="2" placeholder="وصف مختصر..."></textarea>
      </div>
      <label class="form-check-row" for="workshopSectionActive">
        <input type="checkbox" id="workshopSectionActive" checked>
        <span>القسم نشط</span>
      </label>
      <div id="workshopSectionError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;padding:10px;background:#fee2e2;border-radius:8px;"></div>
    </div>
    <div class="catalog-modal-footer">
      <button type="button" class="btn-action" id="cancelWorkshopSectionModal">إلغاء</button>
      <button type="button" class="btn-action success" id="saveWorkshopSectionBtn">💾 حفظ القسم</button>
    </div>
  </div>
</div>

{{-- مودال الفني --}}
<div class="catalog-modal-overlay" id="workshopTechnicianModal" role="dialog" aria-modal="true" aria-labelledby="workshopTechnicianModalTitle">
  <div class="catalog-modal" style="max-width:560px;" onclick="event.stopPropagation()">
    <div class="catalog-modal-header">
      <div>
        <h3 id="workshopTechnicianModalTitle">➕ فني جديد</h3>
        <p class="ws-muted" style="margin:4px 0 0;font-size:12px;" id="workshopTechnicianModalHint">أدخل بيانات الفني واربطه بالأقسام.</p>
      </div>
      <button type="button" class="catalog-modal-close" id="closeWorkshopTechnicianModal" aria-label="إغلاق">&times;</button>
    </div>
    <div class="catalog-modal-body">
      <input type="hidden" id="workshopTechnicianId">
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopTechnicianName">الاسم <span style="color:#dc2626">*</span></label>
        <input type="text" id="workshopTechnicianName" class="form-control" maxlength="255" placeholder="مثال: أحمد محمد">
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopTechnicianUsername">اسم المستخدم <span style="color:#dc2626">*</span></label>
        <input type="text" id="workshopTechnicianUsername" class="form-control" maxlength="50" placeholder="ahmed_m" dir="ltr">
        <small style="display:block;margin-top:4px;color:#64748b;font-size:12px;">حروف إنجليزية وأرقام و _ و - فقط</small>
      </div>
      <div class="form-group" style="margin-bottom:14px;" id="workshopTechnicianPasswordGroup">
        <label for="workshopTechnicianPassword">كلمة المرور <span style="color:#dc2626" id="workshopTechnicianPasswordRequired">*</span></label>
        <input type="password" id="workshopTechnicianPassword" class="form-control" minlength="6" placeholder="6 أحرف على الأقل">
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label for="workshopTechnicianSections">🏭 ربط بالأقسام</label>
        <select id="workshopTechnicianSections" class="form-control" multiple size="6"></select>
        <small style="display:block;margin-top:6px;color:#64748b;font-size:12px;">
          اضغط <strong>Ctrl</strong> (أو <strong>Cmd</strong> على Mac) لاختيار أكثر من قسم.
          @if ($sectionCount === 0)
            <br>لا توجد أقسام — أنشئ قسماً أولاً من تبويب «الأقسام».
          @endif
        </small>
      </div>
      <label class="form-check-row" for="workshopTechnicianActive">
        <input type="checkbox" id="workshopTechnicianActive" checked>
        <span>الفني نشط</span>
      </label>
      <div id="workshopTechnicianError" style="display:none;color:#dc2626;margin-top:12px;font-size:13px;padding:10px;background:#fee2e2;border-radius:8px;"></div>
    </div>
    <div class="catalog-modal-footer">
      <button type="button" class="btn-action" id="cancelWorkshopTechnicianModal">إلغاء</button>
      <button type="button" class="btn-action success" id="saveWorkshopTechnicianBtn">💾 حفظ الفني</button>
    </div>
  </div>
</div>

<script>
window.__WORKSHOP_SECTIONS = @json($sections);
window.__WORKSHOP_TECHNICIANS = @json($technicians);
window.__WORKSHOP_SECTIONS_API = @json($workshop_sections_api ?? '/admin/workshop-sections');
window.__WORKSHOP_TECHNICIANS_API = @json($workshop_technicians_api ?? '/admin/workshop-technicians');
</script>
